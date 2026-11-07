const SISI_MAKS = 1600;
export const HALAMAN_MAKS = 10;

export class PdfTidakBisaDibaca extends Error {}

/**
 * Ubah PDF menjadi satu gambar JPEG per halaman (sisi terpanjang maks 1600 px), di peramban,
 * supaya diperlakukan sama seperti foto: antrean, tata letak, dan cetak tidak perlu tahu PDF.
 * pdf.js dimuat hanya saat ada PDF yang dipilih.
 */
export async function pdfKeGambar(
    file: File,
): Promise<{ berkas: File[]; halamanTotal: number }> {
    const [pdfjs, worker] = await Promise.all([
        import('pdfjs-dist/legacy/build/pdf.mjs'),
        import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'),
    ]);

    pdfjs.GlobalWorkerOptions.workerSrc = worker.default;

    const tugas = pdfjs.getDocument({
        data: new Uint8Array(await file.arrayBuffer()),
    });
    let dokumen;

    try {
        dokumen = await tugas.promise;
    } catch {
        void tugas.destroy();

        throw new PdfTidakBisaDibaca(file.name);
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
        throw galat instanceof PdfTidakBisaDibaca
            ? galat
            : new PdfTidakBisaDibaca(file.name);
    } finally {
        void tugas.destroy();
    }
}
