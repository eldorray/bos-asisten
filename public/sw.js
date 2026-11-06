// Service worker SPJ BOS. Tidak melewati bundler, jadi berkas ini ditulis apa adanya.
const CACHE_ASET = 'spj-aset-v1';
const CACHE_HALAMAN = 'spj-halaman-v1';
const CACHE_INERTIA = 'spj-inertia-v1';
const HALAMAN_OFFLINE = '/offline.html';

// Halaman tersimpan berisi data anggaran, jadi harus hilang saat akun berganti.
const JALUR_GANTI_AKUN = ['/login', '/logout', '/two-factor-challenge'];

function hapusHalaman() {
    return Promise.all([
        caches.delete(CACHE_HALAMAN),
        caches.delete(CACHE_INERTIA),
    ]);
}

// Semua bundel disimpan di muka, supaya halaman yang belum pernah dibuka tetap bisa tampil offline.
async function segarkanAset() {
    const cache = await caches.open(CACHE_ASET);
    const respons = await fetch('/build/manifest.json', { cache: 'no-cache' });

    if (!respons.ok) {
        return;
    }

    const manifest = await respons.json();
    const aset = new Set(['/pwa-192.png', HALAMAN_OFFLINE]);

    for (const entri of Object.values(manifest)) {
        for (const berkas of [
            entri.file,
            ...(entri.css ?? []),
            ...(entri.assets ?? []),
        ]) {
            aset.add(`/build/${berkas}`);
        }
    }

    const tersimpan = new Set(
        (await cache.keys()).map((r) => new URL(r.url).pathname),
    );

    // Satu berkas gagal tidak boleh menggagalkan pemasangan service worker.
    await Promise.allSettled(
        [...aset].filter((a) => !tersimpan.has(a)).map((a) => cache.add(a)),
    );
    await Promise.all(
        [...tersimpan].filter((a) => !aset.has(a)).map((a) => cache.delete(a)),
    );
}

self.addEventListener('install', (event) => {
    event.waitUntil(segarkanAset());
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
    if (event.data === 'segarkan-aset') {
        event.waitUntil(segarkanAset());
    }
});

async function asetDulu(request) {
    const tersimpan = await caches.match(request);

    if (tersimpan) {
        return tersimpan;
    }

    const respons = await fetch(request);

    if (respons.ok) {
        const cache = await caches.open(CACHE_ASET);
        await cache.put(request, respons.clone());
    }

    return respons;
}

function bolehDisimpan(respons) {
    const jenis = respons.headers.get('Content-Type') ?? '';

    // Hanya halaman: PDF dan zip cadangan ikut lewat jalur navigasi yang sama.
    return (
        respons.ok &&
        !respons.redirected &&
        (jenis.includes('text/html') || jenis.includes('application/json'))
    );
}

async function jaringanDulu(request, namaCache, cadangan) {
    const cache = await caches.open(namaCache);

    try {
        const respons = await fetch(request);

        if (bolehDisimpan(respons)) {
            await cache.put(request, respons.clone());
        }

        return respons;
    } catch (galat) {
        const tersimpan = await cache.match(request);

        if (tersimpan) {
            return tersimpan;
        }

        // Pernah dibuka sebagai halaman penuh: suruh Inertia memuatnya lewat jalur navigasi.
        if (
            namaCache === CACHE_INERTIA &&
            (await caches.open(CACHE_HALAMAN).then((c) => c.match(request.url)))
        ) {
            return new Response('', {
                status: 409,
                headers: { 'X-Inertia-Location': request.url },
            });
        }

        const halamanCadangan = cadangan ? await caches.match(cadangan) : null;

        if (halamanCadangan) {
            return halamanCadangan;
        }

        throw galat;
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.method !== 'GET') {
        if (JALUR_GANTI_AKUN.includes(url.pathname)) {
            event.waitUntil(hapusHalaman());
        }

        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(asetDulu(request));

        return;
    }

    if (request.headers.has('X-Inertia')) {
        // Muat ulang sebagian berisi sebagian props; tidak bisa dipakai menggantikan halaman utuh.
        const sebagian =
            request.headers.has('X-Inertia-Partial-Data') ||
            request.headers.has('X-Inertia-Partial-Except');

        if (!sebagian) {
            event.respondWith(jaringanDulu(request, CACHE_INERTIA));
        }

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            jaringanDulu(request, CACHE_HALAMAN, HALAMAN_OFFLINE),
        );
    }
});
