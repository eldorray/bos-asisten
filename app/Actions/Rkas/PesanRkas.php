<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

final class PesanRkas
{
    public const WAJIB = 'Wajib diisi';

    public const SSH_BAWAH = 'Harga kurang dari batas bawah SSH';

    public const SSH_ATAS = 'Harga melebihi batas atas SSH';

    public const URAIAN_MAKS = 'Maksimal 500 karakter';

    public const HARGA_MAKS = 'Maksimal Rp 700.000.000.000';

    public const VOLUME_MAKS = 'Maksimal 6 digit';

    public const VOLUME_KOSONG = 'Isi volume minimal pada satu bulan';

    public const GANDA = 'Kegiatan Tidak Dapat Dianggarkan. Anda sudah menganggarkan kegiatan dengan detail rekening belanja, uraian, dan harga satuan yang sama. Jika Anda ingin menambahkan sesuatu, silakan edit di kegiatan sebelumnya.';

    public const KEGIATAN_BERUBAH = 'Daftar kegiatan berubah. Silakan pilih ulang.';

    public const REKENING_BERUBAH = 'Daftar rekening belanja berubah. Silakan pilih ulang.';

    public const BARANG_BERUBAH = 'Referensi daftar barang/jasa berubah. Silakan pilih ulang.';

    public const BATAS_BERUBAH = 'Batas harga berubah. Silakan masukkan ulang.';

    public const TERKUNCI = 'Kertas kerja ini tidak bisa diubah karena berstatus %s.';

    public const TAHAP_1_MINIMAL = 'Anggaran di bulan Januari-Juni belum mencapai 50% dari dana tersedia di tahap 1.';

    public const SISA = 'Masih ada sisa anggaran sebesar %s dari nominal anggaran Anda.';

    public const LEBIH = 'Masih ada kelebihan anggaran sebesar %s dari nominal anggaran Anda.';

    public const LINTAS_JENIS = 'Sesuai peraturan yang berlaku, Anda tidak boleh melakukan pergeseran lintas jenis belanja (%s).';

    public const REFERENSI_BERMASALAH = 'Masih ada %d baris dengan referensi bermasalah.';

    public const TAHAP_LEBIH = 'Anggaran tahap %d melebihi alokasi sebesar %s.';

    public const HONOR = 'Anggaran pembayaran honor lebih dari maksimal %d%% dari total pagu anggaran.';

    public const BUKU = 'Anggaran penyediaan buku kurang dari minimal %d%% dari total pagu anggaran.';

    public const SARPRAS = 'Anggaran pemeliharaan sarpras lebih dari maksimal %d%% dari total pagu anggaran.';

    public const BULAN_LEWAT = 'Bulan ini sudah lewat; di ARKAS baris yang sudah dibelanjakan tidak bisa diubah.';

    public const BARIS_LEWAT_DIHAPUS = '%d baris yang sudah punya volume di bulan yang lewat dihapus; di ARKAS baris yang sudah dibelanjakan tidak bisa dihapus.';

    public const PAGU_PERGESERAN = 'Pagu tidak bisa diubah pada pergeseran.';

    public const KUOTA_PERGESERAN = 'Anggaran telah mencapai batas pergeseran';

    public const KUOTA_PERUBAHAN = 'Anggaran telah mencapai batas perubahan';

    public const REVISI_TERBUKA = 'Masih ada revisi yang belum disahkan untuk kertas kerja ini.';

    public const BUKAN_ARKAS = 'Draf RKAS hanya untuk sekolah yang memakai ARKAS.';

    public const SUDAH_ADA = 'Kertas kerja untuk sekolah, tahun, dan sumber dana ini sudah ada.';

    public const REFERENSI_DIPAKAI = 'Referensi ini dipakai %d baris dan tidak bisa dihapus.';

    public const BARANG_BUKAN_REKENING = 'Barang/jasa ini bukan untuk rekening belanja yang dipilih.';

    public const PTK_TIDAK_SESUAI = 'Jenis PTK tidak sesuai dengan kegiatan honor ini.';

    public static function rupiah(int $n): string
    {
        return 'Rp '.number_format($n, 0, ',', '.');
    }
}
