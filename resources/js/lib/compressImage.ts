const SISI_MAKS = 1600;

/**
 * Perkecil foto di peramban (tegak sesuai EXIF, sisi terpanjang maks 1600 px, JPEG).
 * Kalau peramban tidak bisa membacanya (misalnya HEIC), kembalikan file asli.
 */
export async function compressImage(file: File): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        const skala = Math.min(1, SISI_MAKS / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * skala));
        canvas.height = Math.max(1, Math.round(bitmap.height * skala));

        const context = canvas.getContext('2d');

        if (!context) {
            bitmap.close();

            return file;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((selesai) =>
            canvas.toBlob(selesai, 'image/jpeg', 0.8),
        );

        if (!blob) {
            return file;
        }

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
            type: 'image/jpeg',
        });
    } catch {
        return file;
    }
}
