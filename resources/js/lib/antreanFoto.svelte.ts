import { page, router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import FotoController from '@/actions/App/Http/Controllers/FotoController';
import { compressImage, FormatTidakDidukung } from '@/lib/compressImage';
import {
    HALAMAN_MAKS,
    PdfTidakBisaDibaca,
    pdfKeGambar,
} from '@/lib/pdfKeGambar';

export type ItemAntrean = {
    kunci: number;
    kegiatanUuid: string;
    jenisBerkasId: number;
    namaFile: string;
    status: 'menunggu' | 'mengunggah' | 'gagal';
    pesan: string | null;
};

type Rekaman = Omit<ItemAntrean, 'kunci' | 'status'> & {
    kunci?: number;
    foto: Blob;
};

type HasilKirim = 'terkirim' | 'jaringan' | 'sesi' | { pesan: string };

const JEDA_ULANG_MS = 30_000;
const HALAMAN_BERFOTO = ['kegiatan/Show', 'Dashboard'];

let daftar = $state<ItemAntrean[]>([]);
let online = $state(true);
let sesiHabis = $state(false);
let berjalan = false;
let basisData: Promise<IDBDatabase> | null = null;

function bukaBasisData(): Promise<IDBDatabase> {
    basisData ??= new Promise((selesai, gagal) => {
        const buka = indexedDB.open('spj-antrean-foto', 1);
        buka.onupgradeneeded = () =>
            buka.result.createObjectStore('foto', {
                keyPath: 'kunci',
                autoIncrement: true,
            });
        buka.onsuccess = () => selesai(buka.result);
        buka.onerror = () => gagal(buka.error);
    });

    return basisData;
}

async function simpanan<T>(
    mode: IDBTransactionMode,
    aksi: (store: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
    const db = await bukaBasisData();

    return new Promise((selesai, gagal) => {
        const permintaan = aksi(
            db.transaction('foto', mode).objectStore('foto'),
        );
        permintaan.onsuccess = () => selesai(permintaan.result);
        permintaan.onerror = () => gagal(permintaan.error);
    });
}

function tokenCsrf(): string {
    const cocok = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return cocok ? decodeURIComponent(cocok[1]) : '';
}

async function kirim(rekaman: Rekaman): Promise<HasilKirim> {
    const data = new FormData();
    data.append('jenis_berkas_id', String(rekaman.jenisBerkasId));
    data.append('foto', rekaman.foto, rekaman.namaFile);

    let respons: Response;

    try {
        // Bukan kunjungan Inertia: antrean jalan terus saat pengguna pindah halaman.
        respons = await fetch(FotoController.store(rekaman.kegiatanUuid).url, {
            method: 'POST',
            body: data,
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': tokenCsrf(),
            },
        });
    } catch {
        return 'jaringan';
    }

    if (respons.ok) {
        return 'terkirim';
    }

    if (respons.status === 401 || respons.status === 419) {
        return 'sesi';
    }

    if (respons.status === 422) {
        const isi = await respons.json().catch(() => null);

        return {
            pesan:
                isi?.errors?.foto?.[0] ??
                isi?.errors?.jenis_berkas_id?.[0] ??
                'Foto ditolak.',
        };
    }

    return respons.status >= 500
        ? { pesan: 'Server bermasalah. Coba lagi nanti.' }
        : { pesan: 'Gagal mengunggah. Coba lagi.' };
}

async function proses(): Promise<void> {
    let adaTerkirim = false;

    for (;;) {
        const item = daftar.find((a) => a.status === 'menunggu');

        if (!item) {
            break;
        }

        item.status = 'mengunggah';
        const rekaman = await simpanan<Rekaman | undefined>('readonly', (s) =>
            s.get(item.kunci),
        );

        // Tab lain sudah mengirimnya.
        if (!rekaman) {
            daftar = daftar.filter((a) => a.kunci !== item.kunci);
            adaTerkirim = true;
            continue;
        }

        // ponytail: respons yang hilang di jalan membuat foto terkirim dua kali; pengguna bisa menghapusnya.
        const hasil = await kirim(rekaman);

        if (hasil === 'terkirim') {
            online = true;
            sesiHabis = false;
            await simpanan('readwrite', (s) => s.delete(item.kunci));
            daftar = daftar.filter((a) => a.kunci !== item.kunci);
            adaTerkirim = true;
        } else if (hasil === 'jaringan') {
            item.status = 'menunggu';
            online = false;
            setTimeout(() => void jalankan(), JEDA_ULANG_MS);
            break;
        } else if (hasil === 'sesi') {
            item.status = 'menunggu';
            sesiHabis = true;
            break;
        } else {
            item.status = 'gagal';
            item.pesan = hasil.pesan;
            await simpanan('readwrite', (s) =>
                s.put({ ...rekaman, pesan: hasil.pesan }),
            );
        }
    }

    if (adaTerkirim && HALAMAN_BERFOTO.includes(page.component)) {
        router.reload({ async: true });
    }
}

async function jalankan(): Promise<void> {
    if (berjalan) {
        return;
    }

    berjalan = true;

    try {
        // Dua tab tidak boleh mengirim foto yang sama.
        if (navigator.locks) {
            await navigator.locks.request('spj-antrean-foto', proses);
        } else {
            await proses();
        }
    } finally {
        berjalan = false;
    }
}

/**
 * PDF dipecah menjadi gambar per halaman; selain PDF dikembalikan apa adanya.
 */
async function sebagaiGambar(file: File): Promise<File[]> {
    if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
        return [file];
    }

    try {
        const hasil = await pdfKeGambar(file);

        if (hasil.halamanTotal > HALAMAN_MAKS) {
            toast.warning(
                `${file.name} punya ${hasil.halamanTotal} halaman; hanya ${HALAMAN_MAKS} halaman pertama yang diunggah.`,
            );
        }

        return hasil.berkas;
    } catch (galat) {
        toast.error(
            galat instanceof PdfTidakBisaDibaca
                ? galat.kataSandi
                    ? `${file.name} diberi kata sandi. Buka kuncinya dulu.`
                    : `${file.name} tidak bisa diproses (${galat.sebab}).`
                : `${file.name} tidak bisa diproses.`,
        );

        return [];
    }
}

export const antreanFoto = {
    get daftar() {
        return daftar;
    },
    get online() {
        return online;
    },
    get sesiHabis() {
        return sesiHabis;
    },

    untuk(kegiatanUuid: string, jenisBerkasId: number): ItemAntrean[] {
        return daftar.filter(
            (a) =>
                a.kegiatanUuid === kegiatanUuid &&
                a.jenisBerkasId === jenisBerkasId,
        );
    },

    async tambah(
        kegiatanUuid: string,
        jenisBerkasId: number,
        berkas: File[],
    ): Promise<void> {
        void navigator.storage?.persist?.();

        for (const asli of berkas) {
            for (const file of await sebagaiGambar(asli)) {
                try {
                    const foto = await compressImage(file);
                    const rekaman: Rekaman = {
                        kegiatanUuid,
                        jenisBerkasId,
                        namaFile: file.name,
                        pesan: null,
                        foto,
                    };
                    const kunci = await simpanan('readwrite', (s) =>
                        s.add(rekaman),
                    );

                    daftar.push({
                        kunci: kunci as number,
                        kegiatanUuid,
                        jenisBerkasId,
                        namaFile: file.name,
                        status: 'menunggu',
                        pesan: null,
                    });
                } catch (galat) {
                    toast.error(
                        galat instanceof FormatTidakDidukung
                            ? `${file.name}: format foto tidak didukung. Ubah ke JPG lalu pilih lagi.`
                            : `${file.name} tidak bisa disimpan di HP. Coba ambil ulang.`,
                    );
                }
            }
        }

        void jalankan();
    },

    ulangi(item: ItemAntrean): void {
        item.status = 'menunggu';
        item.pesan = null;
        void jalankan();
    },

    async buang(item: ItemAntrean): Promise<void> {
        await simpanan('readwrite', (s) => s.delete(item.kunci));
        daftar = daftar.filter((a) => a.kunci !== item.kunci);
    },

    async mulai(): Promise<void> {
        online = navigator.onLine;
        window.addEventListener('online', () => {
            online = true;
            void jalankan();
        });
        window.addEventListener('offline', () => (online = false));
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                void jalankan();
            }
        });
        // Termasuk setelah masuk lagi karena sesi habis.
        router.on('navigate', () => void jalankan());

        try {
            const tersimpan = await simpanan<Rekaman[]>('readonly', (s) =>
                s.getAll(),
            );

            daftar = tersimpan.map((r) => ({
                kunci: r.kunci as number,
                kegiatanUuid: r.kegiatanUuid,
                jenisBerkasId: r.jenisBerkasId,
                namaFile: r.namaFile,
                // Antrean dari versi lama menyimpan id angka, yang tidak lagi dikenali alamatnya.
                status: r.pesan || !r.kegiatanUuid ? 'gagal' : 'menunggu',
                pesan:
                    r.pesan ??
                    (r.kegiatanUuid
                        ? null
                        : 'Foto ini disimpan oleh versi lama. Hapus lalu unggah ulang.'),
            }));
        } catch {
            return;
        }

        void jalankan();
    },
};
