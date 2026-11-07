const SISI_MAKS = 1600;
const FORMAT_AMAN = ['image/jpeg', 'image/png', 'image/webp'];

export class FormatTidakDidukung extends Error {}

type Sumber = {
    gambar: CanvasImageSource;
    lebar: number;
    tinggi: number;
    tutup: () => void;
};

/**
 * Safari iPhone: opsi `imageOrientation: 'from-image'` bisa ditolak (TypeError), jadi coba tanpa opsi,
 * lalu lewat elemen <img> yang selalu menegakkan foto sesuai EXIF.
 */
async function bukaGambar(file: File): Promise<Sumber> {
    for (const opsi of [
        { imageOrientation: 'from-image' as const },
        undefined,
    ]) {
        try {
            const bitmap = await createImageBitmap(file, opsi);

            return {
                gambar: bitmap,
                lebar: bitmap.width,
                tinggi: bitmap.height,
                tutup: () => bitmap.close(),
            };
        } catch {
            // coba cara berikutnya
        }
    }

    const url = URL.createObjectURL(file);
    const img = new Image();

    try {
        img.src = url;
        await img.decode();
    } catch (galat) {
        URL.revokeObjectURL(url);

        throw galat;
    }

    return {
        gambar: img,
        lebar: img.naturalWidth,
        tinggi: img.naturalHeight,
        tutup: () => URL.revokeObjectURL(url),
    };
}

/**
 * Perkecil foto di peramban (tegak sesuai EXIF, sisi terpanjang maks 1600 px, JPEG).
 * Kalau peramban tidak bisa membacanya, file asli dikirim hanya bila formatnya JPG/PNG/WebP;
 * selain itu (misalnya HEIC) dilempar FormatTidakDidukung karena server pasti menolaknya.
 */
export async function compressImage(file: File): Promise<File> {
    try {
        const sumber = await bukaGambar(file);

        try {
            const skala = Math.min(
                1,
                SISI_MAKS / Math.max(sumber.lebar, sumber.tinggi),
            );
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(sumber.lebar * skala));
            canvas.height = Math.max(1, Math.round(sumber.tinggi * skala));

            const context = canvas.getContext('2d');

            if (!context) {
                throw new Error('canvas');
            }

            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(sumber.gambar, 0, 0, canvas.width, canvas.height);

            const blob = await new Promise<Blob | null>((selesai) =>
                canvas.toBlob(selesai, 'image/jpeg', 0.8),
            );

            if (!blob) {
                throw new Error('toBlob');
            }

            return new File(
                [blob],
                file.name.replace(/\.[^.]+$/, '') + '.jpg',
                {
                    type: 'image/jpeg',
                },
            );
        } finally {
            sumber.tutup();
        }
    } catch {
        if (FORMAT_AMAN.includes(file.type)) {
            return file;
        }

        throw new FormatTidakDidukung(file.name);
    }
}
