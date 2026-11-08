# Desain: Modul Draf RKAS (mekanisme seperti ARKAS)

Tanggal: 2026-11-08. Bahasa antarmuka: Indonesia. Bagian dari aplikasi ini (bos-asisten / spj-bos).

Label sumber keputusan, sama dengan desain utama: **[Pemilik]** diputuskan pemilik. **[Disetujui]** usulan yang tidak dikoreksi pemilik saat ditinjau. **[Pilihan penulis]** dipilih penulis desain, boleh ditolak saat membaca dokumen ini.

Rujukan analisis: `/Users/elfahmie/Documents/Coding/rea/analysis/ARKAS-RKAS-MECHANISM.md`. Analisis ini membedah installer ARKAS 4.3.1 tanpa menjalankannya, dan setiap klaim di sana ditandai Terbukti atau Dugaan. Di dokumen ini, rujukan ke analisis ditulis "Analisis §n".

## 1. Tujuan dan alasan

Pemilik menyusun RKAS di ARKAS untuk SMP Garuda. ARKAS mengikat satu NPSN ke satu perangkat, dan ARKAS asli hanya ada di laptop rumah. Pemilik ingin menyusun dan mematangkan RKAS dulu di aplikasi ini, lalu mengetik hasil finalnya ke ARKAS asli. **[Pemilik]**

Supaya hasil draf langsung diterima ARKAS, mekanismenya dibuat sama dengan ARKAS: struktur kertas kerja, pembagian per bulan, aturan validasi, perhitungan, dan revisi. **[Pemilik]** Yang ditiru adalah perilaku dan aturannya, bukan kode, tampilan, logo, aktivasi, atau sinkronisasi ARKAS. **[Disetujui]**

Aplikasi hanya dipakai untuk sekolah pemilik sendiri dan tidak dijual atau dibagikan. **[Pemilik]** Karena itu modul ini digabung ke aplikasi ini, bukan dibuat sebagai aplikasi terpisah. **[Disetujui]**

**Pemakaian pertama: menyusun RKAS 2027 dari awal. [Pemilik]** Revisi RKAS 2026 yang sudah disahkan tidak menjadi prioritas, jadi impor kertas kerja dari `arkas.db` tetap ditunda (§5). Referensi 2027 mungkin belum ada di `arkas.db`, sehingga salin referensi dari 2026 (§4.4) dan input manual menjadi jalur awal yang penting.

**Alur kerja keseluruhan:**

```
Draf RKAS (modul ini) → diketik manual ke ARKAS → disahkan dinas
→ PDF "Rincian Kertas Kerja Perbulan" diimpor seperti sekarang → SPJ
```

**Ukuran keberhasilan:**
- Kertas kerja yang dinyatakan "Siap" di sini lolos pengajuan di ARKAS tanpa ditolak karena aturan yang sebenarnya bisa dicek lebih awal.
- Selisih antara draf dan RKAS yang disahkan langsung terlihat.

## 2. Sasaran dan batasan lingkup

**Sasaran**
- Menyusun kertas kerja per sekolah × sumber dana × tahun, berisi baris belanja dengan volume per bulan.
- Menerapkan aturan ARKAS saat mengisi dan saat menyatakan siap.
- Menerapkan aturan tahap milik pemilik (§7.3).
- Mendukung revisi: pergeseran dan perubahan sebagai versi baru.
- Mencetak rincian kertas kerja sebagai panduan saat mengetik ke ARKAS.
- Membandingkan draf dengan anggaran hasil impor PDF ARKAS.

**Bukan sasaran**
- Mengirim data ke ARKAS, termasuk mengubah `arkas.db`. **[Pemilik, sejalan dengan desain utama §2]**
- Melewati atau meniru aktivasi NPSN maupun pengikatan perangkat. **[Disetujui]**
- Menyalin kode, tampilan, logo, atau template laporan ARKAS. **[Disetujui]**
- Persetujuan oleh dinas. Statusnya ditandai pemilik sendiri (§6). **[Disetujui]**
- MI Daarul Hikmah (ERKAM) pada versi ini. **[Disetujui]** Struktur data tidak boleh menutup kemungkinan ditambah nanti.
- BKU, pajak, dan realisasi. Pajak di ARKAS baru dihitung di BKU, bukan saat perencanaan (Analisis §4).
- Sumber dana selain BOSP Reguler dan Lainnya, misalnya BOS Kinerja, Afirmasi, dan Daerah. **[Pilihan penulis]** Tambah kalau ada kebutuhan nyata.

**Kegagalan walau secara teknis jalan**
- Draf dinyatakan siap, lalu ditolak ARKAS karena aturan yang sebenarnya bisa dicek di sini.
- Angka total, per bulan, atau per tahap di sini berbeda dengan yang ARKAS hitung untuk isian yang sama.
- Draf diam-diam ikut mengubah data SPJ, misalnya membuat atau mengubah `kegiatans`. Data SPJ tetap hanya berasal dari impor PDF ARKAS.
- Revisi menimpa versi yang sudah disahkan, sehingga riwayatnya hilang.

## 3. Batasan teknis

- **Teknologi:** sama dengan desain utama §3, yaitu Laravel 13, Inertia v3, Svelte 5, Tailwind 4, Pest, SQLite tanpa fitur khusus SQLite, dan paket Hostinger terlemah tanpa worker antrean.
- **Konvensi:** ikuti pola repo, yaitu Actions berkata kerja Indonesia, Form Request, `#[Fillable]`, Larastan level 7, dan komponen di `resources/js/components/...`. AGENTS.md melarang folder dasar baru dan dependensi baru tanpa persetujuan. Kalau pembangun merasa perlu salah satunya, tanyakan dulu.
- **Terpisah dari data SPJ [Pilihan penulis]:** modul ini memakai tabel sendiri dan hanya membaca `sekolahs` serta `kegiatans` (untuk perbandingan, §10). Tidak ada tulisan ke tabel SPJ. Menu bisa dimatikan tanpa mengganggu SPJ.
- **Uang dalam rupiah bulat (bigint),** seperti tabel lain. Persentase dihitung dari bilangan bulat, lalu dibulatkan 2 desimal saat ditampilkan, sama dengan ARKAS (Analisis §3.3).

## 4. Konsep dan data

### 4.1 Kertas kerja

Satu kertas kerja = satu sekolah × sumber dana × tahun × **versi** (Analisis §1.1, §1.6).

- **Pagu:**
  - BOSP Reguler: diisi pemilik, karena di ARKAS pagu datang dari server.
  - Lainnya: diisi sebagai daftar pemberi dana (nama + nominal), dan pagu = jumlahnya. Sama dengan ARKAS.
- **Versi:** awal, pergeseran ke-n, atau perubahan ke-n. Setiap versi revisi menunjuk versi sebelumnya (§6.2).
- **Status:** §6.

### 4.2 Baris belanja

Mengikuti `rapbs` ARKAS (Analisis §1.1, §1.3, §4):

- **Kegiatan:** level 3 dari hierarki Program → Komponen → Kegiatan, misalnya `07.12.01.`.
- **Rekening belanja:** kode akun 5.x.
- **Barang acuan (opsional):**
  - Jika rekening punya daftar barang, uraian dipilih dari daftar itu.
  - Memilih barang mengisi otomatis harga dan satuan.
  - Rekening `5.2` tanpa daftar khusus memakai barang yang tidak terikat rekening.
- **Uraian:**
  - Rekening tanpa daftar barang memakai teks bebas, maksimal 500 karakter.
  - Kegiatan honor `07.12.01.`–`07.12.04.` memakai uraian dari daftar PTK dengan format `"<nama> (<NUPTK>)"`, dan baris ditautkan ke PTK tersebut (Analisis §1.3).
- **Satuan:** satu satuan untuk semua bulan, dipilih dari daftar satuan (§4.4), bukan teks bebas, supaya bisa diketik apa adanya ke ARKAS dan pemeriksaan baris ganda tidak lolos karena beda huruf besar/kecil. **[Pilihan penulis]**
- **Harga satuan:** rupiah bulat, minimal 1.
- **Volume per bulan Januari–Desember:** bilangan bulat, maksimal 6 digit. Bulan kosong berarti tidak dianggarkan.
- **Keterangan.**
- **Urutan:** nomor 3 digit per (kertas kerja, kegiatan, rekening), sama dengan ARKAS.

### 4.3 Perhitungan (harus sama dengan ARKAS, Analisis §4)

- Jumlah bulan = volume bulan × harga satuan.
- Volume baris = jumlah volume semua bulan. Jumlah baris = volume baris × harga satuan.
- Sisa pagu = pagu − jumlah semua baris.
- Total tahap = jumlah nilai bulan-bulan dalam tahap itu (§7.3).
- **Jenis belanja**, dari awalan kode rekening:
  - Operasional = `5.1`
  - Alat dan Mesin = `5.2.02`
  - Aset = `5.2` lainnya
- **Rincian jenis belanja untuk ringkasan:**
  - honor = `5.1.02.02.01.0013`
  - barang = `5.1.02.01`
  - jasa = `5.1.02.02` (selain honor)
  - pemeliharaan = `5.1.02.03`
  - perjalanan dinas = `5.1.02.04`
  - peralatan mesin = `5.2.02`/`5.2.04`
  - aset tetap lainnya = `5.2.05`

### 4.4 Data referensi (per tahun)

Mengikuti tabel referensi ARKAS (Analisis §1.1, §5):

- **Kegiatan** (`ref_kode`): kode, induk, uraian, level, tahun, sumber dana, bentuk pendidikan, tanggal kedaluwarsa. Daftar kegiatan disaring per tahun, sumber dana, dan jenjang SMP. Kalau sumber dana tidak punya kode sendiri, dipakai kode BOS Reguler (Analisis §1.2).
- **Rekening** (`ref_rekening`): kode, nama, tahun, tanggal kedaluwarsa. Semua rekening yang masih berlaku ditawarkan untuk semua kegiatan, karena ARKAS juga tidak punya pemetaan kegiatan → rekening (Analisis §1.3).
- **Barang acuan** (`ref_acuan_barang`): nama, satuan, harga, batas bawah, batas atas, rekening (boleh kosong), tahun, tanggal kedaluwarsa.
- **Satuan** (`ref_satuan`): daftar satuan yang dipakai ARKAS. **[Pilihan penulis]**
- **PTK:** nama, NUPTK, jenis (Guru, Administrasi, Perpustakaan, Lainnya). Daftar ini milik sekolah.
- **Salin referensi dari tahun sebelumnya [Pilihan penulis]:** referensi tahun baru bisa dibuat dari salinan tahun sebelumnya, lalu disesuaikan atau ditimpa impor. Ini perlu karena `arkas.db` bisa saja belum memuat referensi tahun yang akan disusun.

### 4.5 Pengaturan per tahun anggaran [Pilihan penulis]

Di ARKAS, nilai-nilai ini dikirim server. Di sini nilainya dipegang pemilik, dengan nilai awal sebagai berikut:

| Pengaturan | Nilai awal | Sumber |
|---|---|---|
| Pembagian tahap | 2 tahap, Jan–Jun / Jul–Des, alokasi 50% / 50% | **[Pemilik]** untuk 50/50, Analisis §1.4 |
| Ambang minimal tahap 1 (pemblokir, cara ARKAS) | 25% dari pagu | Analisis §3.3, lihat §12 |
| Honor maksimal | 20% negeri / 40% swasta | Analisis §3.3 |
| Buku minimal | 10% | Analisis §3.3 |
| Sarpras maksimal | 20% | Analisis §3.3 |
| Daftar kode kegiatan dan rekening untuk honor, buku, dan sarpras | daftar tahun ≥ 2026 untuk SMP dari Analisis §3.3 | Analisis §3.3 |
| Kuota pergeseran dan perubahan | tanpa batas | tidak diketahui (nilai server) |

Status negeri atau swasta disimpan per sekolah, karena menentukan batas honor.

## 5. Mengisi data referensi

- **Sumber utama: `arkas.db` milik sekolah sendiri [Pemilik]**, diambil dari laptop yang sudah teraktivasi. File ini **menyusul**. Pemilik sedang mencarinya.
- **Isi yang diimpor:** kegiatan, rekening, barang acuan, satuan, dan PTK untuk tahun yang dipilih. Data anggaran ARKAS di file itu tidak diimpor pada versi ini. **[Pilihan penulis]** Impor kertas kerja yang sudah disahkan sebagai titik awal pergeseran bisa ditambah nanti.
- **Cara impor:**
  - Pemilik mengunggah salinan file yang diambil saat ARKAS tertutup.
  - File dibaca tanpa diubah dan dihapus setelah impor.
  - Hasil impor ditampilkan dulu (jumlah baris per tabel dan tahun) sebelum disimpan, sama seperti pratinjau impor PDF.
  - Unggah ulang menjadi pembaruan, bukan penghapusan. Entri yang sudah dipakai baris draf tidak dihapus, tetapi ditandai kedaluwarsa.
- **Jika `arkas.db` terenkripsi: berhenti dan tanyakan pemilik. Jangan mencoba membukanya.** Kemungkinan ini belum diperiksa (Analisis "Tidak ditemukan").
- **Sebelum file tersedia [Pilihan penulis]:**
  - Kegiatan, rekening, dan PTK bisa ditambah manual lewat form sederhana.
  - Baris boleh tanpa barang acuan (teks bebas). Pemeriksaan SSH dilewati karena batasnya tidak ada, sama dengan perilaku ARKAS untuk rekening tanpa daftar.
  - Modul tetap bisa dipakai penuh, hanya pilihannya yang terbatas.
- **Fixture uji** dibuat dari data samaran, bukan dari `arkas.db` asli. File asli tidak boleh di-commit karena berisi data PTK. **[Pilihan penulis, sejalan dengan desain utama §11]**

## 6. Status dan revisi

### 6.1 Status kertas kerja [Pilihan penulis, meniru urutan ARKAS Analisis §2]

| Status | Arti | Bisa diubah? |
|---|---|---|
| Draf | Sedang disusun | Ya |
| Siap | Lolos semua pemeriksaan pemblokir (§7.2), tinggal diketik ke ARKAS | Tidak. Bisa dikembalikan ke Draf. |
| Disahkan | Pemilik menandai bahwa RKAS ini sudah disahkan dinas di ARKAS | Tidak. Perubahan hanya lewat revisi (§6.2). |

- Kertas kerja hanya bisa dihapus saat berstatus Draf, sama dengan ARKAS.
- Label "Kertas Kerja" berubah menjadi "RKAS" setelah Disahkan, sama dengan ARKAS.

### 6.2 Pergeseran dan perubahan (Analisis §1.6)

- **Siapa yang bisa direvisi:** hanya versi Disahkan.
- **Cara membuat versi baru:** revisi membuat kertas kerja baru berstatus Draf, berisi salinan semua baris. Setiap salinan menunjuk baris asalnya. Versi lama tetap ada dan bisa dibaca.
- **Pergeseran:**
  - Pagu tetap.
  - Saat menyatakan Siap, total tiap jenis belanja (Operasional, Alat dan Mesin, Aset) harus sama dengan versi sebelumnya.
- **Perubahan:**
  - Pagu boleh diganti.
  - Nomor perubahan bertambah, dan hitungan pergeseran mulai lagi dari nol, sama dengan pengodean ARKAS.
- **Kuota:** mengikuti pengaturan tahun (§4.5).
- **Bulan yang sudah lewat [Pilihan penulis]:**
  - ARKAS mengunci baris yang sudah terealisasi di BKU (Analisis §1.6). Modul ini tidak punya data realisasi, jadi tidak mengunci.
  - Sebagai gantinya, setiap revisi punya tanggal revisi.
  - Mengubah volume pada bulan sebelum tanggal itu memunculkan peringatan, yang tidak memblokir: "Bulan ini sudah lewat; di ARKAS baris yang sudah dibelanjakan tidak bisa diubah."
  - Modul tetap tidak membaca data SPJ (kuitansi) untuk realisasi.

## 7. Aturan validasi

Pesan memakai kalimat ARKAS, atau yang semakna, supaya pemilik mengenalinya (Analisis §3). **[Pilihan penulis]**

### 7.1 Saat menyimpan baris (menolak simpan)

- **Wajib diisi:** kegiatan, rekening, uraian, satuan, dan harga (≥ 1). Pesan: "Wajib diisi".
- **Batas SSH**, hanya jika baris memakai barang acuan:
  - Harga < batas bawah: "Harga kurang dari batas bawah SSH".
  - Harga > batas atas (hanya jika batas atas > 0): "Harga melebihi batas atas SSH".
- **Batas isian:** volume per bulan maksimal 6 digit, uraian maksimal 500 karakter.
- **Baris ganda:** kegiatan + rekening + uraian + harga + satuan sama dalam satu kertas kerja. Pesan: "Kegiatan Tidak Dapat Dianggarkan", disertai penjelasan untuk mengedit baris sebelumnya.
- **Referensi kedaluwarsa:** kegiatan, rekening, atau barang yang sudah kedaluwarsa tidak bisa dipilih untuk baris baru.
  - Baris lama yang memakainya ditandai "Daftar … berubah. Silakan pilih ulang." dan menahan status Siap.
  - Begitu juga baris yang harganya kini di luar batas SSH baru, atau yang uraiannya tidak lagi sama dengan nama barang (Analisis §3.2).

### 7.2 Saat menyatakan Siap (pemblokir), urutan seperti ARKAS (Analisis §3.3)

Untuk BOSP Reguler:

1. **Tahap 1 minimal:** total Januari–Juni ≥ ambang tahap 1 dari pengaturan, dengan nilai awal 25% pagu. Pesan meniru ARKAS: "…anggaran di bulan Januari-Juni belum mencapai 50% dari dana tersedia di tahap 1…".
2. **Total harus sama persis dengan pagu.** Pesan: "Masih ada sisa atau kelebihan anggaran dari nominal anggaran Anda…", disertai angka sisa atau kelebihannya.
3. **Khusus pergeseran:** total tiap jenis belanja sama dengan versi sebelumnya. Pesan: "…tidak boleh melakukan pergeseran lintas jenis belanja."
4. **Tidak ada baris bertanda referensi bermasalah** (§7.1).

Untuk sumber dana Lainnya, hanya nomor 2 dan 4 yang berlaku, sama dengan ARKAS (Analisis §7.5).

### 7.3 Aturan tahap milik pemilik (peringatan) **[Pemilik]**

- **Pembagian bulan:** pagu tahunan dicairkan dua tahap. Tahap 1 = Januari–Juni, tahap 2 = Juli–Desember.
- **Alokasi tahap:**
  - Tahap 1 = pagu × persentase tahap 1, **dibulatkan ke bawah** ke rupiah.
  - Tahap 2 = pagu − alokasi tahap 1.
  - Selisih keduanya paling banyak Rp1. **[Pilihan penulis]**
- **Peringatan:** muncul bila total salah satu tahap melebihi alokasinya, dengan menyebut tahap dan kelebihannya. Peringatan **tidak memblokir**. Karena total juga harus sama dengan pagu (§7.2), tidak ada tahap yang lebih berarti kedua tahap pas di alokasinya.
- **Kapan tampil:** selalu terlihat di ringkasan (alokasi, dianggarkan, sisa atau kelebihan per tahap), dan ditampilkan lagi saat menyatakan Siap.
- **Lingkup:** hanya untuk BOSP Reguler. Tidak berlaku untuk sumber dana Lainnya. **[Pilihan penulis, mengikuti perlakuan ARKAS]**
- **Bisa diatur:** persentasenya ada di pengaturan tahun (§4.5) dengan nilai awal 50/50, misalnya kalau suatu tahun pencairannya 40/60. **[Disetujui]**

### 7.4 Proporsi juknis (peringatan, Analisis §3.3)

Untuk BOSP Reguler. Kode kegiatan dan rekening diambil dari pengaturan tahun.

- **Honor:** jumlah baris pada kegiatan honor dengan rekening honorarium. Melebihi maksimal 20% (negeri) atau 40% (swasta) dari pagu → peringatan.
- **Buku:** jumlah baris pada kegiatan buku. Kurang dari minimal 10% pagu → peringatan.
- **Sarpras:** jumlah baris pada kegiatan sarpras dengan rekening `5.1.*` atau mebel `5.2.02.05.02.0001`. Melebihi maksimal 20% pagu → peringatan.
- Peringatan ditampilkan di ringkasan dan saat menyatakan Siap, dan **tidak memblokir**, sama dengan ARKAS yang tetap bisa "Lanjutkan Pengesahan".

## 8. Layar

- **Daftar kertas kerja:** per sekolah dan tahun, dengan sumber dana, versi, status, pagu, total, dan sisa.
- **Kertas kerja:**
  - **Ringkasan di atas:** pagu, dianggarkan, sisa, kartu tahap 1 dan 2 (§7.3), jenis belanja, persentase juknis beserta peringatannya, serta jumlah baris bermasalah.
  - **Baris dikelompokkan** Program → Komponen → Kegiatan, dengan kolom rekening, uraian, volume, satuan, harga, dan jumlah.
  - **Tampilan per bulan:** bisa dibuka dalam bentuk tabel 12 bulan atau per tahap.
- **Form baris:**
  - Pilih kegiatan, lalu rekening, lalu barang atau uraian, satuan, dan harga.
  - Volume per bulan diisi dalam kisi 12 bulan, dengan total yang langsung terhitung.
  - Untuk kegiatan honor, uraian dipilih dari PTK.
- **Tombol aksi:** "Nyatakan Siap" menampilkan hasil pemeriksaan §7.2–7.4. "Kembalikan ke Draf", "Tandai Disahkan", "Buat Pergeseran", dan "Buat Perubahan" muncul sesuai status.
- **Pengaturan:** pengaturan tahun (§4.5), status negeri/swasta sekolah, impor `arkas.db`, serta input manual kegiatan, rekening, dan PTK.
- **Perangkat:** utamanya dipakai di laptop karena isinya tabel lebar, tetapi harus tetap bisa dibaca di HP. **[Pilihan penulis]** Navigasi ditambahkan di sidebar dan tab bawah seperti menu lain.
- Tampilan meniru urutan isian ARKAS supaya mudah disalin, tetapi tidak meniru desain visualnya.

## 9. Cetak

- **Satu PDF "Rincian Kertas Kerja" [Pilihan penulis]:** berisi kepala (sekolah, NPSN, tahun, sumber dana, versi, status), baris dikelompokkan seperti layar, kolom volume per bulan, jumlah per tahap, total, dan ringkasan.
- **Kegunaan:** dipakai sebagai panduan saat mengetik ke ARKAS, jadi urutannya mengikuti urutan input ARKAS.
- **Bukan format resmi:** template laporan ARKAS tidak dibaca karena terenkripsi (Analisis §6). Kolomnya dirancang sendiri, dan watermark "DRAF — bukan dokumen resmi" selalu dicetak.
- **Pustaka:** memakai pustaka PDF yang sudah ada di repo.
- Varian lain, seperti triwulan, Lembar RKAS, dan Unit 2.2, ditambah kalau dibutuhkan.

## 10. Perbandingan dengan RKAS yang disahkan

- **Data pembanding:** `kegiatans` hasil impor PDF ARKAS untuk sekolah dan tahun yang sama.
- **Jika impor bulan itu belum ada:** tampilkan "Belum diimpor". Jangan dianggap selisih.
- **Tampilan per bulan:**
  - total draf versi Disahkan terakhir vs total impor
  - baris yang hanya ada di salah satu sisi
  - baris yang nilainya berbeda
- **Pencocokan baris:** kode rekening dan uraian, dengan cara yang sama seperti pencocokan impor (desain utama §5.4). Kode program di PDF dicocokkan dengan kode kegiatan bila formatnya bisa disamakan. Cara persisnya diserahkan ke pembangun, setelah dicek terhadap fixture impor yang ada.
- **Hanya membaca.** Tidak ada yang diubah di kedua sisi.

## 11. Keamanan dan data

- **Akses:** semua rute memerlukan login dan semua input lewat Form Request, seperti modul lain.
- **Isi data:** draf berisi nominal dan nama PTK, jadi tidak boleh tampil tanpa login dan hanya kolom yang perlu yang dikirim lewat props.
- **File `arkas.db`:** validasi bahwa file memang SQLite, batasi ukurannya, simpan di disk privat hanya selama impor, lalu hapus. Tidak pernah di-commit.
- **Kalimat yang ditiru:** hanya aturan dan pesan pendek yang ditiru dari ARKAS. Tidak ada kode, aset, atau template ARKAS di repo.

## 12. Hal yang belum pasti

- **Ambang tahap 1 ARKAS:** pesan menyebut "50% dana tahap 1", tetapi rumus di kode memakai 25% pagu. Dugaannya keduanya sama karena tahap 1 = setengah pagu. Pakai 25% pagu sebagai pemblokir. Aturan tahap pemilik (§7.3) lebih ketat, jadi praktis tertutup. Bisa dipastikan dengan mencoba di ARKAS asli.
- **Struktur `arkas.db` produksi:** diasumsikan sama dengan entity di Analisis §1.1. Pastikan begitu file tersedia. Jika berbeda atau terenkripsi, lihat §5.
- **Kuota dan tenggat revisi:** nilai server tidak diketahui, jadi dibuat bisa diatur.

## 13. Pengujian

Minimal:
- Perhitungan jumlah bulan, baris, tahap, dan sisa pagu, termasuk pagu ganjil pada pembagian tahap.
- Setiap aturan §7.1 dan §7.2: kasus lolos dan kasus ditolak, beserta pesannya.
- Peringatan tahap (§7.3) dan juknis (§7.4): muncul pada kondisi yang tepat dan tidak memblokir.
- Sumber dana Lainnya hanya terkena aturan total = pagu.
- Revisi: salinan lengkap, versi lama utuh, pergeseran lintas jenis belanja ditolak, perubahan boleh mengganti pagu, dan peringatan muncul saat bulan sebelum tanggal revisi diubah.
- Salin referensi dari tahun sebelumnya tidak mengubah referensi tahun asal.
- Status: baris tidak bisa diubah saat Siap atau Disahkan, dan hapus hanya saat Draf.
- Impor referensi dari `arkas.db` samaran: pratinjau, pembaruan tanpa menghapus entri yang dipakai, dan penolakan file bukan SQLite atau terenkripsi.
- Perbandingan dengan `kegiatans`: bulan yang belum diimpor tidak dihitung selisih.
- Modul tidak pernah menulis ke tabel SPJ.
- Rute tidak bisa diakses tanpa login.

## 14. Diserahkan ke pembangun

Struktur tabel dan nama kolom, susunan komponen dan layar, cara membaca `arkas.db` (setelah file tersedia), detail pencocokan baris pada perbandingan, tata letak PDF, dan urutan pengerjaan. Saran urutannya: kertas kerja dan baris dengan referensi manual dulu, lalu aturan, revisi, cetak, perbandingan, dan terakhir impor `arkas.db`.
