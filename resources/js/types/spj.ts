export type StatusKode =
    | 'selesai'
    | 'kurang'
    | 'belum'
    | 'dirapel'
    | 'tidak_dilaksanakan'
    | 'di_luar_aplikasi';

export type BerkasStatus = {
    nama: string;
    ada: boolean;
    jumlahFoto: number;
};

export type BarisKegiatanData = {
    id: number;
    nama: string;
    koefisien: string;
    jumlah: number;
    bulan: number | null;
    tahap: number | null;
    jenis: string;
    status: StatusKode;
    berkas: BerkasStatus[];
    kurang: number;
    catatanStatus: string | null;
    rapel: { bulan: number; nomor: string } | null;
    hilangDariAnggaran: boolean;
    perluDitinjau: boolean;
};

export type SekolahBulan = {
    id: number;
    nama: string;
    sistem: 'arkas' | 'erkam';
    total: number;
    peringatan: string | null;
    kegiatan: BarisKegiatanData[];
    belumBerbulan: { tahap: number; kegiatan: BarisKegiatanData[] }[];
};

export const namaBulan = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

export const rupiah = (angka: number): string =>
    angka.toLocaleString('id-ID');
