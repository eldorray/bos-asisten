const SISI_MAKS = 1600;
export const HALAMAN_MAKS = 10;

export class PdfTidakBisaDibaca extends Error {
    constructor(
        nama: string,
        readonly kataSandi = false,
        readonly sebab = '',
    ) {
        super(nama);
    }
}

function sebabDari(galat: unknown): string {
    return galat instanceof Error
        ? `${galat.name}: ${galat.message}`.slice(0, 120)
        : String(galat).slice(0, 120);
}

/**
 * Ubah PDF menjadi satu gambar JPEG per halaman (sisi terpanjang maks 1600 px), di peramban,
 * supaya diperlakukan sama seperti foto: antrean, tata letak, dan cetak tidak perlu tahu PDF.
 * pdf.js dimuat hanya saat ada PDF yang dipilih.
 */
export async function pdfKeGambar(
    file: File,
): Promise<{ berkas: File[]; halamanTotal: number }> {
    let pdfjs;

    try {
        const [pustaka, worker] = await Promise.all([
            import('pdfjs-dist/legacy/build/pdf.mjs'),
            import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'),
        ]);

        pdfjs = pustaka;
        pdfjs.GlobalWorkerOptions.workerSrc = worker.default;
    } catch (galat) {
        console.error('pdf.js gagal dimuat', galat);

        throw new PdfTidakBisaDibaca(file.name, false, sebabDari(galat));
    }

    const tugas = pdfjs.getDocument({
        data: new Uint8Array(await file.arrayBuffer()),
    });
    let dokumen;

    try {
        dokumen = await tugas.promise;
    } catch (galat) {
        console.error('PDF gagal dibuka', galat);
        void tugas.destroy();

        throw new PdfTidakBisaDibaca(
            file.name,
            galat instanceof Error && galat.name === 'PasswordException',
            sebabDari(galat),
        );
    }

    try {
        const dasar = file.name.replace(/\.[^.]+$/, '');
        const jumlah = Math.min(dokumen.numPages, HALAMAN_MAKS);
        const berkas: File[] = [];

        for (let nomor = 1; nomor <= jumlah; nomor++) {
            const halaman = await dokumen.getPage(nomor);
            const ukuran = halaman.getViewport({ scale: 1 });
            const viewport = halaman.getViewport({
                scale: SISI_MAKS / Math.max(ukuran.width, ukuran.height),
            });
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(viewport.width));
            canvas.height = Math.max(1, Math.round(viewport.height));

            await halaman.render({
                canvas,
                viewport,
                background: '#ffffff',
            }).promise;
            halaman.cleanup();

            const blob = await new Promise<Blob | null>((selesai) =>
                canvas.toBlob(selesai, 'image/jpeg', 0.8),
            );

            if (!blob) {
                throw new PdfTidakBisaDibaca(file.name);
            }

            berkas.push(
                new File(
                    [blob],
                    dokumen.numPages > 1
                        ? `${dasar}-hal${nomor}.jpg`
                        : `${dasar}.jpg`,
                    { type: 'image/jpeg' },
                ),
            );
        }

        return { berkas, halamanTotal: dokumen.numPages };
    } catch (galat) {
        console.error('PDF gagal digambar', galat);

        throw galat instanceof PdfTidakBisaDibaca
            ? galat
            : new PdfTidakBisaDibaca(file.name, false, sebabDari(galat));
    } finally {
        void tugas.destroy();
    }
}
