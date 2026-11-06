# Desain: Aplikasi Pembantu SPJ BOS (ARKAS & ERKAM)

Tanggal: 2026-11-05. Bahasa antarmuka: Indonesia.

Label sumber keputusan: **[Pemilik]** diputuskan pemilik. **[Disetujui]** usulan yang tidak dikoreksi pemilik saat ditinjau. **[Pilihan penulis]** dipilih penulis desain, boleh ditolak saat membaca dokumen ini.

## 1. Tujuan dan alasan

Pemilik menyusun SPJ dana BOS untuk dua sekolah:

| Sekolah | Sistem anggaran | Instansi |
|---|---|---|
| SMP Garuda | ARKAS | Dinas |
| MI Daarul Hikmah (MIS Daarul Hikmah) | ERKAM | Kemenag |

SPJ seharusnya dikerjakan tiap bulan. Karena harus membuka dua aplikasi dan mencari kegiatan bulan berjalan, ada yang terlewat. Berkas contoh pemilik menunjukkan gejalanya: kuitansi internet MI ada untuk Januari–Maret dan Mei, tetapi tidak untuk April dan Juni. Ada juga salah ketik ("Lma belas juta") dan tanggal lunas tahun 2025 pada anggaran 2026.

**Ukuran keberhasilan:** cukup membuka satu layar untuk tahu apa yang harus dilaporkan bulan ini dan berkas apa yang kurang, lalu mengunduh satu PDF per sekolah per bulan yang siap dicetak dan ditandatangani.

## 2. Sasaran dan batasan lingkup

**Sasaran**
- Mengimpor anggaran dari PDF ARKAS dan ERKAM.
- Menampilkan kegiatan kedua sekolah per bulan, lengkap dengan status berkasnya.
- Membuat kuitansi otomatis.
- Menerima foto berkas dari kamera atau unggahan.
- Menghasilkan PDF bulanan per sekolah dengan susunan yang hemat kertas.

**Bukan sasaran**
- Mengirim data ke ARKAS, ERKAM, atau sistem lain. **[Pemilik]**
- Membuat undangan, daftar hadir, atau notulen. Dokumen ini harus asli, jadi difoto. **[Pemilik]**
- Pembukuan seperti BKU atau laporan pajak. **[Disetujui]**
- Banyak pengguna, banyak yayasan, peran, atau modul. Aplikasi hanya untuk pemilik. **[Pemilik]**
- Pengingat lewat email atau WhatsApp. **[Pilihan penulis]** Bisa ditambah nanti kalau membuka aplikasi tiap bulan ternyata masih terlewat.

**Kegagalan walau secara teknis jalan**
- Ada kegiatan yang hilang diam-diam, baik karena PDF salah terbaca, PDF bulan itu belum diunggah, maupun kegiatan ERKAM yang belum diberi bulan.
- Unggah ulang anggaran menghapus foto yang sudah terkumpul.
- Foto bukti dapat diakses tanpa login.
- Mengambil foto dari HP terasa merepotkan.

## 3. Batasan teknis

- **Teknologi [Pemilik]:** repo ini apa adanya, Laravel 13, Inertia v3, Svelte 5, Tailwind 4, Fortify, Wayfinder, Pest. Bangun di atas layout dan autentikasi starter kit.
- **Hubungan dengan `CLAUDE.md` folder induk:** dokumen itu menjelaskan platform yayasan bersama (Vue, MySQL, `yayasan_id`, modul nwidart). Aplikasi ini berdiri sendiri, jadi aturan arsitektur itu tidak berlaku. **[Pemilik]** Standar kode, keamanan, dan pengujian di sana (§4, §5, §8) tetap diikuti. **[Pilihan penulis]**
- **Basis data [Pilihan penulis]:** SQLite seperti konfigurasi repo sekarang, cukup untuk satu pengguna. Kode tidak boleh bergantung pada fitur khusus SQLite.
- **Pemasangan [Pemilik]:** online di Hostinger, bisa dibuka dari mana saja, dengan login. Domain ditanyakan ke pemilik saat tahap pemasangan.
- **Paket hosting belum diketahui [Pilihan penulis]:** rancang untuk paket terlemah, yaitu tanpa worker antrean yang selalu jalan, tanpa program sistem tambahan, dan dengan batas memori. Periksa paket sebenarnya lewat akun Hostinger pemilik saat menyusun rencana, lalu longgarkan kalau memungkinkan.
- **Hindari ketergantungan pada program sistem** seperti `pdftotext`, karena belum tentu tersedia di hosting. **[Pilihan penulis]**

## 4. Alur penggunaan

1. **Unggah anggaran.** ARKAS per bulan (bisa beberapa file sekaligus), ERKAM per tahun.
2. **Periksa impor.** Aplikasi menampilkan jumlah kegiatan dan mencocokkan totalnya dengan total di PDF sebelum data disimpan.
3. **Atur bulan.** ARKAS otomatis masuk bulannya. Kegiatan rutin ERKAM dipecah otomatis. Kegiatan sekali belanja ERKAM dipilih bulannya oleh pemilik.
4. **Kerjakan bulan berjalan.** Isi data kuitansi dan ambil foto berkas per kegiatan.
5. **Unduh PDF bulanan** per sekolah, cetak, lalu tanda tangani kuitansinya. **[Disetujui]**

## 5. Impor anggaran

Contoh format: `01_Kertas_Kerja_Januari_2026.pdf` dan `03_Kertas_Kerja_Maret_2026.pdf` (ARKAS, di `~/Downloads`), serta `erkam2026.pdf` (ERKAM, di `~/Documents`). Semuanya ada di komputer pemilik, di luar repo, dan berisi teks, bukan hasil pindai.

### 5.1 ARKAS: "Rincian Kertas Kerja Perbulan"
- **Kepala dokumen:** NPSN, Nama Sekolah, Bulan ("Januari 2026"), Sumber Dana ("BOSP Reguler").
- **Bagian "B. Belanja":** baris berkode rekening adalah kegiatan yang dilaporkan. Baris tanpa kode rekening adalah judul kelompok program, jangan dijadikan kegiatan.
- **Kolom yang disimpan:** kode rekening, kode program, uraian, volume, satuan, tarif, jumlah.
- **Uraian bisa dua baris.** Pada teks hasil ekstraksi, angka berada di tengah, jadi potongan uraian muncul sebelum dan sesudah baris angka.
- **Tabel bisa berpindah halaman** dengan kepala tabel berulang, dan tanda tangan bisa jatuh di halaman sendiri (contoh Maret: 3 halaman).
- **Kode rekening + kode program tidak unik.** Keempat gaji memakai kode yang sama, begitu juga Jamuan Makan dan Snack. Uraian wajib ikut dalam pencocokan.
- **Pengecekan:** jumlah semua kegiatan harus sama dengan baris "Jumlah" (Januari: Rp15.100.000, Maret: Rp19.224.000; keduanya cocok saat dijumlahkan manual).

### 5.2 ERKAM: "Rencana Kegiatan dan Anggaran Madrasah (RKAM)"
- **Kepala dokumen:** Madrasah, NSM, Tahun Anggaran.
- **Dua blok:** "APBN - BOS Tahap 2" dan "APBN - BOS Tahap 1". Di contoh, Tahap 2 muncul lebih dulu.
- **Kegiatan:** baris dengan koefisien dan harga, uraian diawali `>> Nama | Deskripsi`. Baris berkode seperti `3.2` atau `3.2.1` adalah judul kelompok.
- **Kolom yang disimpan:** kode kelompok terdekat, uraian, koefisien (jumlah dan satuan), harga, pajak, jumlah, tahap.
- **Baris terpotong antarhalaman.** Contoh: baris 12 Tahap 2 dan baris 5 Tahap 1, uraiannya berlanjut di halaman berikutnya setelah kepala tabel berulang.
- **Pengecekan:** jumlah kegiatan tiap tahap harus sama dengan "Sub Total" (contoh: Rp160.075.000 per tahap).

### 5.3 Pembagian bulan ERKAM
- **Tahap I = Januari–Juni, Tahap II = Juli–Desember.** **[Disetujui]**
- **Rutin:** satuan koefisien "bulan". Contoh "6 bulan": listrik, internet, retribusi, gaji. Dipecah jadi satu kegiatan per bulan mulai bulan pertama tahapnya, nominal per bulan = kolom Harga.
- **Sekali belanja:** selain itu. Contoh di Tahap I ada 10: Filling Cabinet, perangkat jaringan, naskah soal, hosting, pagar besi, spidol, tinta, HVS, nasi kotak, snack. Masuk daftar "belum punya bulan" sampai dipilih. Satu kegiatan hanya punya satu bulan. **[Pilihan penulis]**

### 5.4 Aturan impor
- **Total tidak cocok → impor ditolak**, dengan pesan yang menyebut angka yang diharapkan dan yang terbaca. **[Pilihan penulis]** Lebih baik gagal daripada kegiatan hilang diam-diam.
- **Sekolah ditentukan dari NPSN/NSM di PDF.** Identitas yang tidak dikenal ditolak. **[Pilihan penulis]**
- **Hanya BOS reguler [Pemilik]:** satu PDF ARKAS per bulan dan satu RKAM ERKAM per tahun. PDF atau blok bersumber dana lain ditolak dengan pesan yang jelas, bukan diabaikan diam-diam. **[Pilihan penulis]**
- **Unggah ulang menjadi pembaruan, bukan penghapusan.** Anggaran bisa direvisi. **[Pilihan penulis]** Kegiatan dicocokkan lewat sekolah, tahun, bulan atau tahap, kode, dan uraian:
  - Yang cocok: nilainya diperbarui, berkas dan pilihan bulan tetap.
  - Yang baru: ditambahkan.
  - Yang hilang tanpa berkas: dihapus.
  - Yang hilang tetapi sudah punya berkas: tetap ada dan ditandai "tidak ada lagi di anggaran" untuk diputuskan pemilik.
- **Cara membaca PDF diserahkan ke pembangun, tetapi harus diuji dulu.** `pdftotext -layout` terbukti terbaca baik di kedua contoh, tetapi tidak boleh diandalkan di hosting (§3). Langkah pertama pembangunan adalah percobaan singkat pustaka PHP murni terhadap ketiga contoh. Kalau tidak andal, pertimbangkan membaca PDF di browser.

## 6. Layar bulanan (layar utama)

Contoh layar yang ditinjau pemilik: bagian 1 di `mockups/kuitansi-dan-layar-bulanan.html`. Pemilik tidak mengoreksinya. Status centang di contoh hanya rekaan.

- **Pilih bulan dan tahun.** Default bulan berjalan. Dua sekolah tampil dalam satu layar, masing-masing dengan total nominal.
- **Tiap baris kegiatan:** nama, rincian, nominal, status tiap berkas, dan status keseluruhan (Selesai, Kurang N, Belum).
- **Ringkasan** jumlah kegiatan per status di atas.
- **Kotak "kegiatan belum punya bulan"** dengan pilihan bulan langsung di barisnya, dikelompokkan per tahap.
- **Peringatan anti-terlewat [Pilihan penulis]:**
  - Kalau PDF ARKAS bulan itu belum diunggah, tampilkan "PDF ARKAS bulan ini belum diunggah". Jangan tampilkan daftar kosong seolah tidak ada kegiatan. Hal yang sama berlaku untuk RKAM ERKAM tahun itu.
  - Pemilih bulan menampilkan jumlah kegiatan yang belum selesai di tiap bulan, supaya bulan lampau yang tertinggal terlihat.
- **Kegiatan yang tidak dilaksanakan** bisa ditandai "Tidak dilaksanakan" beserta alasan singkat, supaya tidak menggantung sebagai "Belum". Kegiatan ini tidak masuk PDF. **[Pilihan penulis]**
- **Kegiatan yang sudah dikerjakan di luar aplikasi [Pemilik]:** aplikasi mulai dipakai November 2026, sementara anggaran mencakup sejak Januari. Tiap kegiatan bisa ditandai "Sudah di luar aplikasi", dihitung selesai, dan tidak masuk PDF. Tanda ini bisa dibatalkan. Sediakan juga tombol untuk menandai banyak kegiatan dalam satu bulan sekaligus, dengan kegiatan yang dikecualikan bisa dipilih. **[Pilihan penulis]** Yang benar-benar tertinggal tetap terlihat dan bisa dikerjakan di aplikasi.
- **HP adalah perangkat utama saat memotret.** Layar harus nyaman di lebar ponsel. Contoh layar yang berupa tabel lebar boleh menjadi kartu di HP.

## 7. Jenis kegiatan dan berkas

- **Dinamis [Pemilik]:** tiap kegiatan punya jenis, dan jenis menentukan daftar berkas beserta urutannya. Pemilik bisa menambah dan mengubah jenis serta daftarnya dari aplikasi.
- **Kuitansi selalu berkas pertama** dan dibuat oleh aplikasi. Berkas lain berupa foto.
- **Satu jenis berkas bisa berisi beberapa foto**, misalnya daftar hadir dua halaman atau beberapa foto dokumentasi.
- **Beberapa baris anggaran untuk satu acara [Pemilik]:** contoh "Nasi Kotak" dan "Snack" untuk 42 guru di ERKAM, serta "Jamuan Makan" dan "Jamuan Ringan" untuk 20 orang di ARKAS Maret. Baris konsumsi berjenis Rapat/Pelatihan dengan kode kelompok yang sama disarankan untuk digabung, keputusan tetap di pemilik. **[Pilihan penulis]** Pemilik bisa menggabungkan baris-baris itu menjadi satu acara. Tiap baris tetap punya kuitansi sendiri, tetapi undangan, daftar hadir, notulen, dan foto cukup diunggah sekali dan dipakai bersama. Baris dalam satu acara harus dari sekolah dan bulan yang sama, serta berjenis sama. **[Pilihan penulis]** Status tiap baris dihitung dari kuitansinya sendiri dan dokumen bersama.
- **Saran jenis otomatis** dari kata kunci pada uraian, bisa diubah per kegiatan. **[Disetujui]** Kalau tidak ada yang cocok, kegiatan memakai jenis "Lainnya" dan tampil sebagai perlu ditinjau.

Isi awal yang bisa diubah:

| Jenis | Berkas, berurutan | Sumber |
|---|---|---|
| Langganan (listrik, internet) | Kuitansi, Struk bayar | [Pemilik] |
| Rapat/Pelatihan | Kuitansi, Undangan, Daftar hadir, Notulen, Dokumentasi foto | [Pemilik] |
| Gaji/Honor | Kuitansi | [Pilihan penulis], mengikuti berkas contoh |
| Belanja barang | Kuitansi, Nota, Foto barang | [Pilihan penulis] |
| Lainnya | Kuitansi | [Pilihan penulis] |

## 8. Foto berkas

- **Dua cara [Pemilik]:** kamera dari aplikasi atau unggah file. `<input type="file" accept="image/*" capture="environment">` sudah cukup untuk kamera, jadi tidak perlu pustaka kamera.
- **Format:** gambar saja pada versi pertama. **[Pilihan penulis]** Struk digital berbentuk PDF diunggah sebagai tangkapan layar. Perlu saat ada kebutuhan nyata: dukungan menggabungkan PDF.
- **Ukuran:** diperkecil di browser sebelum diunggah agar hemat kuota dan PDF akhir tidak terlalu besar. Orientasi foto harus benar. Foto bisa dihapus dan diganti.
- **Keamanan:** validasi mime dan ukuran di server, simpan di disk privat, ditampilkan hanya lewat rute yang memerlukan login.

## 9. Kuitansi

Semua kolom terisi otomatis dari anggaran kecuali yang disebut di bawah.

- **Input pemilik:** penerima dan tanggal lunas. Penerima terakhir untuk kegiatan yang sama diisikan otomatis. **[Pilihan penulis]**
- **"Untuk Pembayaran" bisa diubah [Pilihan penulis]:** teks anggaran sering berbeda dari yang ditulis pemilik di kuitansi, misalnya "Gaji/Honor Rutin PTK/Operator" menjadi "Honor GBPNS April 2026 a/n Abdul Hamid dkk", dan uraian ARKAS terlalu panjang untuk sepertiga lembar. Saat pertama, isian diisi nama kegiatan dan bulan. Untuk ARKAS, nama kegiatan diambil dari bagian uraian sebelum tanda "-" pertama, misalnya "Bola Volley-Bola Volley" menjadi "Bola Volley" dan "Gaji Petugas Keamanan-Tipe : ..." menjadi "Gaji Petugas Keamanan". Setelah diubah, kata-katanya diingat untuk bulan berikutnya pada kegiatan yang sama, dengan nama bulan diganti otomatis. Unggah ulang anggaran tidak menimpanya.
- **Jumlah:** default dari anggaran, boleh diubah karena tagihan bisa berbeda. **[Pilihan penulis]** Terbilang selalu dihitung dari jumlah. Tanggal lunas harus berada di tahun anggaran.
- **Nomor bukti otomatis, urut per sekolah dan tahun [Pilihan penulis]:** MI memakai pola yang ada, `NNN/T1/MIDH/2026`. SMP memakai `NNN/01/SMPG/2026` (nomor/bulan/kode sekolah/tahun). Nomor bisa diubah. Nomor awal bisa diatur per sekolah dan tahun, supaya tidak bentrok dengan kuitansi yang sudah dibuat di luar aplikasi.
- **Rapel [Pemilik, dari contoh]:** satu kuitansi bisa mencakup beberapa bulan dari kegiatan yang sama, contoh "Honor Januari–Maret" Rp47.880.000. Jumlahnya default penjumlahan bulan-bulan itu. Kuitansi masuk PDF bulan terakhir cakupannya. Di layar bulan lain, kegiatan itu tampil "dirapel di kuitansi Maret" dan dihitung selesai. **[Pilihan penulis]**
- **Pengaturan per sekolah:** nama sekolah, alamat, kode nomor bukti, nama penandatangan. Peran keduanya berbeda antarsekolah, jadi tidak boleh ditulis di kode:
  - MI: Kepala Madrasah Dra. Nurjanah, Bendahara Fahmie Al Khudhorie, S.Pd.
  - SMP: Kepala Sekolah Fahmie Al Khudhorie, Bendahara Dra. Nurjanah.

**Isi per format**
- **ARKAS [Pemilik memilih format B, 3 per lembar, di `mockups/kuitansi-dan-layar-bulanan.html`]:** untuk pembayaran, sumber dana, jumlah, terbilang, nomor, tanggal lunas, tiga tanda tangan (kepala, bendahara, penerima). Kode rekening dan kode program tidak terlihat di contoh yang dipilih; tambahkan kalau masih muat.
- **ERKAM [Disetujui]:** mengikuti `KUITANSI LUNAS BAYAR ERKAM.doc` milik pemilik: Tahun Anggaran, Nomor Bukti, Sudah terima dari, Madrasah, Desa/Kecamatan, Kabupaten, Provinsi, Jumlah Uang, Terbilang, Untuk Pembayaran, Sumber Dana ("Dana BOS Tahap 1 2026"), Penerima, tanggal lunas, Kepala Madrasah, Bendahara. Diperkecil ke sepertiga A4, tidak lagi satu halaman penuh.

## 10. PDF bulanan

- **Satu PDF per sekolah per bulan [Disetujui]**, ukuran A4 potret.
- **Susunan [Pemilik]:** tiap kuitansi di atas, lalu struk dan dokumen kegiatannya langsung di bawahnya, sesuai urutan berkas jenis kegiatan.
- **Tata letak (disetujui pemilik, contoh di `mockups/susunan-pdf-bulanan.html`):**
  - Kuitansi memakai sepertiga lembar. Sisa lembar diisi lampiran pertama.
  - Lampiran berikutnya maksimal 2 per lembar, ditumpuk.
  - Foto dokumentasi memakai kisi 2 kolom.
  - Kuitansi tanpa lampiran yang berurutan berbagi lembar, maksimal 3 per lembar.
  - Foto mengikuti ruang tanpa terpotong atau gepeng.
- **Satu acara dengan beberapa baris [Pilihan penulis]:** semua kuitansinya berurutan dulu, maksimal 3 per lembar, lalu dokumen bersama satu kali saja di bawahnya. Tidak dicetak ganda.
- **Urutan kegiatan:** mengikuti nomor bukti. Satu acara tetap berdekatan, ditempatkan menurut nomor bukti terkecilnya. **[Pilihan penulis]**
- **Berkas belum lengkap [Pilihan penulis]:** pemilik menyebut unduh dilakukan setelah semua terkumpul. Kalau masih kurang, tombol tetap bisa dipakai setelah peringatan yang menyebut kegiatan dan berkas yang kurang, supaya bisa dicetak sebagai draf.
- **Pembuatan PDF:** pustaka diserahkan ke pembangun. Harus diuji dengan satu bulan berisi banyak foto, dalam batas memori dan waktu hosting.

## 11. Keamanan dan data

- **Pendaftaran akun dimatikan.** `config/fortify.php` sekarang mengaktifkan `Features::registration()`, dan ini harus dimatikan sebelum online. Akun dibuat lewat perintah artisan, tanpa password bawaan.
- **Semua rute memerlukan login,** kecuali halaman login dan fitur akun bawaan. Pembatasan percobaan login tetap aktif.
- **Validasi lewat Form Request.** Kirim hanya kolom yang diperlukan lewat props Inertia.
- **PDF asli pemilik tidak boleh di-commit** ke repo. PDF dan foto berisi nama orang dan nominal. File di `mockups/` juga memuat nama dan nominal asli, jadi commit hanya kalau repo privat. Untuk pengujian, buat contoh yang disamarkan atau simpan di lokasi yang diabaikan git.
- **Cadangan:** foto bukti dan basis data harus punya cara dicadangkan, minimal bisa diunduh pemilik. Bentuknya diserahkan ke pembangun, dengan melihat fitur Hostinger.

## 12. Pengujian

Minimal:
- Pembacaan kedua format, termasuk baris terpotong antarhalaman dan penolakan saat total tidak cocok.
- Pemecahan kegiatan rutin ERKAM per bulan.
- Unggah ulang tidak menghapus berkas.
- Rapel dan statusnya di beberapa bulan.
- Satu acara dengan beberapa baris: dokumen bersama muncul sekali di PDF.
- Kegiatan "Sudah di luar aplikasi" dihitung selesai dan tidak masuk PDF.
- Terbilang, termasuk angka besar.
- Foto tidak bisa diakses tanpa login, dan pendaftaran akun tertutup.
- PDF bulanan berisi urutan yang benar.

## 13. Diserahkan ke pembangun

Struktur tabel, pustaka baca PDF dan pembuat PDF (setelah diuji), penataan layar dan komponen, cara memperkecil foto, cara mencadangkan, serta aturan kata kunci untuk saran jenis.
