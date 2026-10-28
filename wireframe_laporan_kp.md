# Dokumentasi Lengkap Wireframe UI - Sistem Informasi Apotek Kinanthi

Dokumen ini memuat perancangan antarmuka visual (*low-fidelity wireframe*) dalam format monokrom sketsa (*Balsamiq/blueprint style*) untuk keperluan Bab Perancangan Antarmuka Sistem pada Laporan Kerja Praktik (KP).

---

# BAGIAN I: MODUL ADMINISTRATOR (THE BRAIN)

---

## 1. Wireframe Halaman Login (Autentikasi)

![Wireframe Halaman Login](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/login_wireframe_1792949665174.jpg)

### Rincian Komponen Login:
- **Header**: Logo placeholder, judul *Apotek Kinanthi*, dan subjudul *PHARMACY MANAGEMENT SYSTEM | LOGIN PAGE*.
- **Input Form**: Kolom teks *Email Address* dan *Password*.
- **Utilitas**: Checkbox "Ingatkan Saya" dan tautan "Lupa Password?".
- **Tombol Masuk**: Tombol utama *Masuk* (*full-width action button*).

---

## 2. Wireframe Dashboard Admin

![Wireframe Dashboard Admin](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/dashboard_wireframe_v2_1792951001204.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi (Kiri)**: Menu *Dashboard (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Dashboard* dan lencana *Administrator* di pojok kanan atas.
- **8 Kartu KPI (2 Baris x 4 Kolom)**:
  1. *Transaksi Kasir*
  2. *Pendapatan* (Omset)
  3. *Menunggu Harga* (Antrean penetapan harga)
  4. *Faktur Masuk PBF* (Verifikasi fisik)
  5. *Katalog Inventaris SKU*
  6. *Alert Stok Kritis* (disertai indikator progress bar defisit)
  7. *Prioritas FEFO* (Peringatan kedaluwarsa terdekat)
  8. *Audit Stock Opname* (Sesi draft fisik)
- **2 Grafik Analitik**: Tren Penjualan & Transaksi (2/3 lebar) dan 5 Obat Terlaris (1/3 lebar).
- **Tabel Monitoring Persediaan**: Tabel real-time 5 kolom dengan filter dan paginasi.

---

## 3. Wireframe Halaman Katalog Faktur (Katalog Obat & Sediaan)

![Wireframe Katalog Faktur](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/katalog_faktur_v2_1792951028466.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Katalog Faktur (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Katalog Obat & Sediaan* dan lencana *Administrator* di pojok kanan atas.
- **Konsol Operasional**: Tab filter (*Semua Sediaan*, *Stok Menipis*, *Dekat ED*), tombol `+ Input Faktur`, dan search bar global.
- **Stat Cards Kanan**: *Total Data Faktur* dan tautan ke *Produk Racikan*.
- **Tabel Data**: 6 Kolom data sediaan lengkap dengan badge golongan obat dan rincian harga multi-satuan.

---

## 4. Wireframe Halaman Data Supplier PBF

![Wireframe Data Supplier PBF](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/supplier_wireframe_1792951398036.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Supplier / PBF (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Data Supplier PBF* dan lencana *Administrator* di pojok kanan atas.
- **Konsol Operasional**: Tab filter (*Semua PBF*, *PBF Aktif*, *Perlu Tinjauan*), tombol `+ Tambah Supplier`, dan search bar.
- **Stat Cards Kanan**: *DISTRIBUTOR AKTIF* dan *PERLU TINJAUAN*.
- **Tabel Data Supplier**: 6 Kolom data identitas distributor, kontak, alamat, status, dan aksi edit/hapus.

---

## 5. Wireframe Halaman Data Bahan Baku

![Wireframe Data Bahan Baku](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/bahan_baku_wireframe_1792951419099.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Bahan Baku (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Data Bahan Baku* dan lencana *Administrator* di pojok kanan atas.
- **Konsol Operasional**: Tab filter (*Semua Bahan*, *Stok Kritis*, *Siap Racik*), tombol `+ Tambah Bahan`, dan search bar.
- **Stat Cards Kanan**: *STOK KRITIS* dan *SIAP RACIK*.
- **Tabel Data Bahan Baku**: 9 Kolom data bahan racikan, golongan, konversi satuan, harga beli, dan threshold.

---

## 6. Wireframe Halaman Data Obat Racikan

![Wireframe Data Obat Racikan](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/racikan_wireframe_1792951648557.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Produk Racikan (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Data Obat Racikan* dan lencana *Administrator* di pojok kanan atas.
- **Konsol Operasional**: Tab filter (*Semua Racikan*, *Dekat Batas BUD*, *Aman*) dan search bar produk formula racikan.
- **Stat Cards Kanan**: *KATALOG FAKTUR* (`Buka Faktur ->`) dan *DATA RACIKAN* (`12 Formula`).
- **Tabel Data Racikan**: 8 Kolom data formula, nomor batch, tanggal peracikan, batas tanggal BUD, kuantitas, status BUD, dan tombol aksi detail.

---

## 7. Wireframe Halaman Penetapan Harga Jual

![Wireframe Penetapan Harga Jual](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/pricing_wireframe_1792951671868.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Penetapan Harga (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Penetapan Harga Jual* dan lencana *Administrator* di pojok kanan atas.
- **Bagian 1 (Harga Produk Racikan)**: Filter tabs, pencarian, dan tabel margin harga racikan.
- **Bagian 2 (Harga Obat Jadi)**: Filter tabs, pencarian, dan tabel margin harga obat bertingkat.

---

## 8. Wireframe Halaman Stock Opname Admin

![Wireframe Stock Opname Admin](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/stock_opname_wireframe_1792951697916.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Stock Opname (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Stock Opname Admin* dan lencana *Administrator* di pojok kanan atas.
- **Konsol Operasional**: Tab filter (*Semua Sesi*, *Draft*, *Selesai*), tombol `+ Buat Stock Opname`, filter tanggal, dan tombol reset.
- **Stat Cards Kanan**: *SESI AKTIF / DRAFT* dan *AUDIT SELESAI*.
- **Tabel Riwayat Opname**: 6 Kolom data sesi, PIC, jumlah item, status, dan tombol aksi.

---

## 9. Wireframe Halaman Distribusi Stok (Cart Transfer)

![Wireframe Distribusi Stok](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/distribusi_wireframe_1792951940482.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Distribusi Stok (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Distribusi Stok (Cart)* dan lencana *Administrator* di pojok kanan atas.
- **Cockpit Bar**: Dropdown pemilih akun kasir tujuan (*Kasir Tujuan: Kasir 1 ▼*) dan kolom pencarian obat.
- **Split Workspace Area**:
  - **Katalog Obat (Kiri 65%)**: Tab kategori BPOM (*Semua Sediaan*, *Kasir Kosong*, *Obat Keras*, *Bebas*) dan grid kartu obat dengan komparasi stok fisik (*Stok Kasir* vs *Stok Gudang*) serta tombol `+ Distribusi`.
  - **Keranjang Transfer (Kanan 35%)**: Panel keranjang transfer item terpilih dengan pengatur kuantitas (*stepper +/-*), badge satuan kemasan, dan tombol submit besar `Kirim ke Kasir`.

---

## 10. Wireframe Halaman Faktur Masuk PBF

![Wireframe Faktur Masuk PBF](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/faktur_masuk_wireframe_1792951965227.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Faktur Masuk (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Faktur Masuk PBF* dan lencana *Administrator* di pojok kanan atas.
- **Konsol Operasional**:
  - Tab filter: *Semua Faktur (14)*, *Belum Ada Foto (2)*, *Lengkap (12)*.
  - Tombol Aksi: `+ Input Faktur` untuk mencatat penerimaan pengadaan baru.
  - Filter Input: Pencarian nomor faktur/supplier dan dropdown filter distributor (*Semua PBF*).
- **Stat Cards Kanan**:
  - `TOTAL BELANJA`: Akumulasi nilai bruto faktur termasuk PPN.
  - `AUDIT BERKAS`: Jumlah faktur yang membutuhkan unggahan bukti foto fisik nota.
- **Daftar Faktur (Accordion Card)**:
  - Header kartu faktur memuat nomor faktur, nama PBF, tanggal, jumlah item, nominal harga, tombol pintas `Lihat Faktur` / `+ Upload Nota`, dan tombol ekspansi.
  - Tabel rincian barang: 7 Kolom (*Nama Obat*, *Batch*, *Exp Date*, *Qty*, *Harga Beli*, *Disc %*, *Jumlah Harga*).

---

## 11. Wireframe Halaman Laporan Penjualan (Rekap Produk)

![Wireframe Laporan Penjualan](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/laporan_penjualan_wireframe_1793002117419.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi**: Menu *Laporan Penjualan (ACTIVE)* diarsir abu-abu.
- **Top Bar Header**: Judul *Laporan Penjualan (Rekap Produk)* dan lencana *Administrator* di pojok kanan atas.
- **Bar Filter & Ekspor**:
  - Filter Rentang Waktu: Input kalender awal (*Start Date*) dan kalender akhir (*End Date*).
  - Tombol Ekspor: Tombol `Export PDF` (laporan siap cetak) dan tombol `Export Excel` (rekap spreadsheet).
- **5 Kartu Ringkasan Finansial**:
  1. *Total Item Terjual* (Total volume penjualan dalam satuan PCS).
  2. *Total Omzet* (Akumulasi pendapatan kotor).
  3. *Total Modal (HPP)* (Nilai modal pokok pembelian barang).
  4. *Total Laba* (Keuntungan bersih operasional penjualan).
  5. *Rata-Rata Margin* (Persentase rasio laba bersih terhadap omzet).
- **Tabel Rekapitulasi Produk**:
  - Kolom Data: *No*, *Produk & Kategori*, *Satuan*, *HPP Modal*, *Harga Jual*, *Qty Awal*, *Terjual*, *Sisa Stok*, *Total Omzet*, *Laba Bersih*, *Margin %*, dan *Status Stok*.
  - Kontrol paginasi di bagian bawah.

---

# BAGIAN II: MODUL KASIR (POINT OF SALE)

---

## 12. Wireframe Dashboard Kasir (Dashboard / Antrean)

![Wireframe Dashboard Kasir](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/kasir_dashboard_v3_1793020449841.jpg)

### Rincian Struktur Komponen (Sesuai Design System Kasir):
- **Sidebar Navigasi Kasir (Kiri)**:
  - Header: Logo Apotek Kinanthi + teks `Apotek Kinanthi`.
  - Group Header: Label kategori `TRANSAKSI`.
  - Item Menu: `Dashboard / Antrean` (aktif diarsir dengan lencana penanda `ACTIVE`) dan menu kedua `Penjualan OTC` dengan ikon keranjang belanja.
  - Footer Profil Akun: Avatar pengguna kasir, nama `Kasir Kinanthi`, dan peran `Kasir`.
- **Top Bar Header**:
  - Judul Halaman: `Dashboard / Antrean` di sisi kiri.
  - Lencana Peran: Pill badge `Kasir` di pojok kanan atas.
- **Bento Cockpit Header (Top Area)**:
  - **Konsol Operasional (Kiri 8 Kolom)**:
    - Badge kategori: `● POINT OF SALE · KASIR APOTEK`.
    - Judul Layar: `Antrean Pembayaran & Kasir` dan deskripsi singkat operasional.
    - Input Pencarian: Kolom pencarian responsif `[🔍 Cari no. resep, nama pasien, atau dokter...]`.
    - Tombol Aksi Pintas: `+ Kasir Obat Bebas (OTC)` untuk membuka modul kasir OTC langsung.
  - **2 Live Metrik Kartu (Kanan 4 Kolom)**:
    1. `ANTREAN RESEP`: Menampilkan jumlah resep dokter aktif yang siap diproses bayar (`3 Resep - Siap diproses bayar`).
    2. `KAS LACI FISIK`: Total akumulasi uang tunai shift aktif kasir (`Rp 850.000 - Uang fisik shift ini`).
- **Bagian 1: Antrean Resep Siap Bayar**:
  - Header seksi: Judul antrean dan badge counter `3 Antrean Aktif`.
  - Tabel Antrean (6 Kolom): `No Resep`, `Pasien`, `Dokter & Tgl`, `Jml Item`, `Usia Resep` (dengan indikator status aman/segera), dan kolom `Aksi` berisi tombol `Proses Bayar`.
- **Bagian 2: Buku Kas & Riwayat Transaksi (Shift Berjalan)**:
  - Header seksi: Judul transaksi, badge `Shift Berjalan`, filter rentang tanggal `[Tanggal] ▼`, dan tombol cetak `[🖨️ Cetak Slip Shift]`.
  - Micro Stat Ribbon (3 Kolom Ringkasan Finansial):
    1. `TOTAL OMZET SHIFT`: Total penjualan shift berjalan (`Rp 1.450.000` / `12 Lunas`).
    2. `NON-TUNAI (QRIS / TRANSFER)`: Akumulasi transaksi digital EDC/QRIS (`Rp 600.000` / `Digital`).
    3. `TRANSAKSI BATAL (VOID)`: Jumlah nota pembatalan kasir (`0 Nota` / `Nihil`).
  - Tabel Riwayat Transaksi Kasir (7 Kolom):
    - Kolom Data: `No Invoice`, `Pasien`, `Total Pembelian`, `Metode` (Tunai / QRIS), `Status` (Lunas / Void), `Waktu`, dan `Aksi` (tautan `Lihat Struk` & tombol otorisasi `Void`).
  - Paginasi navigasi di pojok kanan bawah `[ < ] [ 1 ] [ > ]`.

---

## 13. Wireframe Kasir POS - Penjualan Bebas (OTC)

![Wireframe Kasir POS OTC](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/kasir_otc_wireframe_1793002171167.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Kasir (Kiri)**: Menu *Penjualan Bebas (OTC)* aktif diarsir abu-abu (`ACTIVE`).
- **Top Bar Header**: Judul *Kasir POS - Penjualan Bebas (OTC)* dan lencana `Kasir` di pojok kanan atas.
- **Toolbar Pencarian & Sortir**:
  - Input teks pencarian nama obat atau scan barcode produk langsung.
  - Tombol sortir cepat (*Urutkan: A-Z / Stok / Harga*).
- **Split Workspace Modern POS**:
  - **Katalog Produk (Kiri 65%)**:
    - Saklar Tab Jenis Produk: *Obat Jadi (120)* vs *Produk Racikan (15)*.
    - Tab Filter Kategori Regulasi BPOM: *Semua*, *Obat Bebas*, *Bebas Terbatas*, dan *Obat Keras (K)*.
    - Grid Kartu Produk: Menampilkan placeholder foto produk, nama sediaan, harga jual per satuan, dan tombol `+` untuk memasukkan ke keranjang kasir secara instan.
  - **Panel Keranjang Kasir (Kanan 35%)**:
    - Header Keranjang: Menampilkan jumlah item pesanan aktif dan tombol `Reset`.
    - Daftar Item Pesanan: Kartu mikro produk terpilih lengkap dengan pengatur kuantitas `[-] [Qty] [+]` dan kalkulasi subtotal.
    - Kalkulasi Finansial: Subtotal, Diskon, PPN, dan Total Tagihan.
    - Tombol Aksi Utama: Tombol checkout besar `Bayar / Checkout (F8)` dengan dukungan pintasan keyboard.

---

# BAGIAN III: MODUL APOTEKER (CLINICAL & COMPOUNDING)

---

## 14. Wireframe Dashboard Apoteker

![Wireframe Dashboard Apoteker](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/dashboard_apoteker_grey_sidebar_1793033036630.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Apoteker (Kiri)**:
  - Header: Logo Apotek Kinanthi + teks `Apotek Kinanthi`.
  - Group Header: `DASHBOARD`, `OPERASIONAL KLINIS`, `INVENTORY`, dan `AUDIT & KEPATUHAN`.
  - Menu Aktif: `Dashboard (ACTIVE)` diarsir abu-abu dengan lencana `ACTIVE`.
  - Menu Lain: `Racikan Aktif`, `Resep Pasien`, `Formula Obat`, `Cek Stok Obat`, `BUD Tracker`, dan `Laporan SIPNAP`.
  - Footer Profil: Avatar pengguna `Apoteker Kinanthi` dan peran `Apoteker`.
- **Top Bar Header**: Judul `Dashboard` di sisi kiri dan lencana `Apoteker` di pojok kanan atas.
- **8 Kartu KPI Klinis & Mutu (2 Baris x 4 Kolom)**:
  - **Baris 1 (Layanan Resep Hari Ini)**:
    1. *Menunggu Skrining*: Antrean resep baru yang membutuhkan verifikasi telaah klinis & administratif (*2 Resep*).
    2. *Sedang Diracik*: Resep dalam tahap penimbangan bahan & pencampuran sediaan (*1 Resep*).
    3. *Siap Diserahkan*: Resep tuntas diracik/dikemas dan siap diserahkan kepada pasien (*4 Resep*).
    4. *Resep Selesai Hari Ini*: Resep yang telah tervalidasi dan diserahkan (*18 Resep*).
  - **Baris 2 (Mutu Farmasi & Kepatuhan Legal)**:
    5. *Peringatan BUD*: Batch obat racikan dengan batas waktu pakai (*Beyond Use Date*) $\le$ 7 hari (*3 Batch*).
    6. *Antrean Lab Racikan*: Batch sediaan racikan yang membutuhkan verifikasi fisik / penetapan harga admin (*2 Batch*).
    7. *Bahan Baku Kritis*: Bahan formularium di bawah ambang batas minimum stok (*1 Bahan*).
    8. *Monitoring SIPNAP*: Pengawasan sediaan Narkotika & Psikotropika regulasi Kemenkes (*14 Item Regulasi*).
- **2 Grafik Analisis Klinis**:
  - **Grafik Kiri (8 Kolom)**: *Tren Layanan Resep* (Area/Line chart dinamika volume resep harian: Resep Masuk vs Resep Selesai dengan filter periode *7 Hari Terakhir*).
  - **Grafik Kanan (4 Kolom)**: *Bentuk Sediaan Racikan* (Donut chart distribusi batch kemasan farmasi: Puyer, Kapsul, Salep, Sirup dengan total *15 Batch* di pusat cincin).
- **Tabel Monitoring Antrean Terintegrasi**:
  - Tab Switcher Segmen: Tab `Antrean Resep (5)` vs Tab `Batch Lab Peracikan (2)` serta tombol pintas `Lihat Semua`.
  - Kolom Tabel (5 Kolom): *No. Resep & Pasien*, *Dokter & Faskes*, *Waktu & Jenis*, *Status Antrean* (dengan badge warna), dan tombol aksi `Detail`.

---

## 15. Wireframe Halaman Racikan Aktif (Log Racikan Lab)

![Wireframe Racikan Aktif](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/racikan_aktif_grey_sidebar_1793033060180.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Apoteker (Kiri)**: Menu `Racikan Aktif` di bawah grup *OPERASIONAL KLINIS* aktif diarsir abu-abu (`ACTIVE`).
- **Top Bar Header**: Judul `Racikan Aktif` dan lencana `Apoteker` di pojok kanan atas.
- **Bento Cockpit Header**:
  - **Konsol Operasional (Kiri 8 Kolom)**:
    - Tab filter status: *Semua Sediaan*, *Menunggu Verifikasi (2)*, *Aktif*, dan *Ditolak*.
    - Tombol aksi utama: `+ Buat Racikan` untuk mencatat formulasi batch peracikan baru.
    - Search bar responsif: `[🔍 Cari nama racikan, formula, ID batch...]`.
  - **2 Stat Cards Kanan (4 Kolom)**:
    1. `VERIFIKASI AKHIR`: Jumlah batch racikan menunggu konfirmasi mutu/harga (*2 Perlu konfirmasi mutu*).
    2. `RACIKAN SIAP`: Akumulasi batch sediaan aktif apotek (*12 Sediaan aktif apotek*).
- **Toolbar & Filter Kemasan**:
  - Subjudul: *Daftar Riwayat Pengajuan Racikan*.
  - Dropdown filter: *Bentuk Kemasan: [Semua Satuan ▼]* (Bungkus, Kapsul, Botol, Tube, Pot) dan tombol `Reset`.
- **Tabel Riwayat Pengajuan Racikan (7 Kolom)**:
  - Kolom data: *Nama Racikan / Batch*, *Formula*, *Items* (jumlah komponen bahan), *Target Hasil* (kuantitas & satuan kemasan), *Tgl Racik / BUD*, *Status* (*Menunggu Harga*, *Aktif*), dan tombol aksi `Detail`.
  - Paginasi `[ < ] [ 1 ] [ 2 ] [ > ]` di pojok kanan bawah.

---

## 16. Wireframe Halaman Resep Pasien (Daftar Resep Aktif)

![Wireframe Resep Pasien](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/apoteker_resep_v3_1793020862608.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Apoteker (Kiri)**: Menu `Resep Pasien` di bawah grup *OPERASIONAL KLINIS* aktif diarsir abu-abu (`ACTIVE`).
- **Top Bar Header**: Judul `Resep Pasien` dan lencana `Apoteker` di pojok kanan atas.
- **Bento Cockpit Header**:
  - **Konsol Operasional (Kiri 8 Kolom)**:
    - Tab filter alur resep: *Aktif*, *Menunggu*, *Diracik*, *Siap Ambil (4)*, dan *Semua Status*.
    - Tombol aksi utama: `+ Input Resep` untuk memasukkan data resep dokter baru ke sistem.
    - Search bar: `[🔍 Cari nomor resep, pasien, dokter, faskes...]`.
  - **2 Stat Cards Kanan (4 Kolom)**:
    1. `PERLU DIPROSES`: Akumulasi resep dalam antrean awal atau proses peracikan (*3 Resep baru / peracikan*).
    2. `SIAP DIAMBIL`: Resep yang telah selesai diracik dan menunggu penyerahan di kasir (*4 Menunggu pasien di kasir*).
- **Toolbar Filter Tambahan**:
  - Filter Tanggal: Input kalender `[📅 Tanggal Resep]` dan tombol `Reset Filter`.
- **Tabel Daftar Resep Pasien (7 Kolom)**:
  - Kolom data:
    - *No. Resep*: Menampilkan nomor kode resep disertai lencana peringatan khusus `Resep Narkotika / Psikotropika - Wajib Cek Fisik Resep & SIP` pada resep obat keras tertentu.
    - *Pasien*: Nama lengkap pasien.
    - *Dokter*: Nama dokter penulis resep.
    - *Tgl Resep*: Tanggal penulisan resep.
    - *Jml Item*: Total kuantitas R/ obat dalam resep.
    - *Status*: Lencana alur resep (*Menunggu*, *Diracik*, *Siap Ambil*).
    - *Aksi*: Tombol `Detail` untuk membuka lembar telaah resep dan peracikan.
  - Paginasi `[ < ] [ 1 ] [ 2 ] [ > ]` di sudut kanan bawah.

---

## 17. Wireframe Halaman Formula Obat (Protokol Peracikan)

![Wireframe Formula Obat](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/apoteker_formula_final_complete.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Apoteker (Kiri)**: Menu `Formula Obat` di bawah grup *OPERASIONAL KLINIS* aktif diarsir abu-abu (`ACTIVE`).
- **Top Bar Header**: Judul `Formula Obat` dan lencana `Apoteker` di pojok kanan atas.
- **Bento Cockpit Header**:
  - **Konsol Operasional (Kiri 8 Kolom)**:
    - Tab filter kategori sediaan: *Semua Kategori*, *Padat / Kapsul*, *Cair / Sirup*, dan *Topikal / Salep*.
    - Tombol aksi utama: `+ Buat Formula` untuk mendaftarkan protokol racikan baku baru.
    - Search bar: `[🔍 Cari nama formula, instruksi racik, atau sediaan...]`.
  - **2 Stat Cards Kanan (4 Kolom)**:
    1. `FORMULA AKTIF`: Total pustaka formula standar apotek (*14 Master racikan tercatat*).
    2. `KATEGORI SEDIAAN`: Keberagaman bentuk sediaan racikan (*3 Kategori terklasifikasi*).
- **Grid Katalog Kartu Formula (Cards Layout)**:
  - Setiap kartu formula memuat:
    - Nama formula racikan standar (misal: *Puyer Batuk Anak*, *Salep Hidrokortison Kustom*).
    - Lencana kategori sediaan dan jumlah komponen bahan aktif.
    - Box instruksi peracikan klinis (*Standard Operating Procedure* pencampuran).
    - Tombol aksi pintas di pojok kanan atas: `Salin` (duplikasi formula) dan `Edit` (sunting protokol).
  - Paginasi navigasi `[ < ] [ 1 ] [ 2 ] [ > ]` di pojok kanan bawah.

---

## 18. Wireframe Halaman Cek Stok Obat (FEFO Batch Tracker)

![Wireframe Cek Stok Obat](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/apoteker_stok_v2_1793021211456.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Apoteker (Kiri)**: Menu `Cek Stok Obat` di bawah grup *INVENTORY* aktif diarsir abu-abu (`ACTIVE`).
- **Top Bar Header**: Judul `Cek Stok Obat` dan lencana `Apoteker` di pojok kanan atas.
- **Bento Cockpit Header**:
  - **Konsol Operasional (Kiri 8 Kolom)**:
    - Tab switcher: *Bahan Baku Racikan (18)* (aktif) vs *Obat Jadi Komersial (120)*.
    - Lencana sistem aktif: `● Urutan FEFO Aktif` (First Expired, First Out).
    - Input pencarian cepat: `[🔍 Cari nama atau kode bahan baku / obat jadi...]`.
  - **2 Stat Cards Kanan (4 Kolom)**:
    1. `MENDEKATI ED (≤ 90 HARI)`: Peringatan batch yang mendekati kedaluwarsa untuk diprioritaskan (*4 Prioritas racik / FEFO*).
    2. `KEDALUWARSA`: Indikator batch kedaluwarsa yang memerlukan tindakan karantina (*0 Stok bebas kedaluwarsa*).
- **Daftar Rincian Stok FEFO (Accordion Grid 2-Kolom)**:
  - Header kartu bahan: Avatar kode sediaan, nama bahan/obat, dan total saldo ketersediaan fisik.
  - Rincian ekspansi batch:
    - Nomor batch unik (#BATCH).
    - Tanggal kedaluwarsa dengan penanda prioritas hijau `FEFO` pada batch kedaluwarsa terdekat.
    - Kode barang dan bentuk sediaan.
  - Paginasi `[ < ] [ 1 ] [ 2 ] [ > ]`.

---

## 19. Wireframe Halaman BUD Tracker (Monitoring Beyond Use Date)

![Wireframe BUD Tracker](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/apoteker_bud_final_complete.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Apoteker (Kiri)**: Menu `BUD Tracker` di bawah grup *INVENTORY* aktif diarsir abu-abu (`ACTIVE`).
- **Top Bar Header**: Judul `Monitoring Beyond Use Date (BUD)` dan lencana `Apoteker` di pojok kanan atas.
- **Bento Cockpit Header**:
  - **Konsol Operasional (Kiri 8 Kolom)**:
    - Tab filter status kestabilan: *Semua Status*, *Peringatan (≤ 7 Hari)*, dan *Aman & Stabil*.
    - Dropdown pengurutan: `[Urut: Terdekat ▼]`.
    - Input pencarian: `[🔍 Cari racikan, nomor resep, pasien, atau batch...]`.
  - **2 Stat Cards Kanan (4 Kolom)**:
    1. `PERINGATAN (≤ 7 HARI)`: Batch racikan yang mendekati batas maksimal waktu simpan (*3 Mendekati masa kedaluwarsa*).
    2. `BUD AMAN`: Batch racikan dalam kondisi stabil dan siap konsumsi (*12 Masa pakai dalam batas aman*).
- **Daftar Racikan Aktif Terpantau (Cards Grid 2-Kolom)**:
  - Setiap kartu racikan menampilkan:
    - Lencana status stabilitas: `[Aman]`, `[Peringatan]`, atau `[Kritis]`.
    - Nama sediaan racikan dan nama pasien pemilik resep.
    - Hitung mundur sisa hari pakai (misal: *3 hari lagi*, *12 hari lagi*).
    - Batas tanggal kedaluwarsa BUD (*Batas: DD/MM/YYYY*).
  - Paginasi `[ < ] [ 1 ] [ 2 ] [ > ]` di pojok kanan bawah.

---

## 20. Wireframe Halaman Laporan SIPNAP (Kepatuhan Regulasi Narkotika & Psikotropika)

![Wireframe Laporan SIPNAP](C:/Users/rifqi/.gemini/antigravity-ide/brain/1d11ff39-f8f9-4fef-b4e8-6c3fc5fecae5/apoteker_sipnap_v2_1793020371106.jpg)

### Rincian Struktur Komponen:
- **Sidebar Navigasi Apoteker (Kiri)**:
  - Menu `Laporan SIPNAP` di bawah grup *AUDIT & KEPATUHAN* aktif terpilih (`ACTIVE`).
  - Hierarki grup konsisten dengan modul Apoteker:
    - `DASHBOARD`: Dashboard
    - `OPERASIONAL KLINIS`: Racikan Aktif, Resep Pasien, Formula Obat
    - `INVENTORY`: Cek Stok Obat, BUD Tracker
    - `AUDIT & KEPATUHAN`: Laporan SIPNAP
    - Footer Profil: `Apoteker Kinanthi` (`Apoteker`).
- **Top Bar Header**:
  - Judul halaman: `Laporan Mutasi SIPNAP`.
  - Lencana peran pengguna: `Apoteker` di sudut kanan atas.
- **Bento Hero Section & Stat Cards**:
  - **Hero Banner Rekapitulasi (Span 8 Kolom)**:
    - Label regulasi resmi: `Permenkes RI · Kepatuhan SIPNAP`.
    - Judul: `Rekapitulasi Mutasi SIPNAP`.
    - Deskripsi: `Laporan agregasi pergerakan persediaan Narkotika & Psikotropika periode ... untuk pelaporan Kemenkes.`
    - Kontrol Filter & Unduhan:
      - Pemilih Periode: Input tanggal tipe bulan `[ type="month" ]`.
      - Tombol Aksi: `[ Export PDF ]` untuk mencetak berkas laporan resmi mutasi obat golongan khusus SIPNAP Kemenkes RI.
  - **Stat Cards KPI Regulasi (Span 4 Kolom)**:
    1. `TOTAL ITEM`: Menghitung total sediaan berklasifikasi khusus dengan rincian sub-penghitungan *Narkotika* dan *Psikotropika*.
    2. `MUTASI BULAN INI`: Ringkasan akumulasi pergerakan fisik barang antara *Pemasukan (Faktur PBF)* dan *Pengeluaran (Resep Pasien)*.
- **Tabel Mutasi SIPNAP (8 Kolom Standar Kemenkes RI)**:
  1. `No`: Nomor urut baris data.
  2. `Identitas Obat`: Nama resmi obat disertai nomor batch unik sediaan.
  3. `Golongan`: Lencana penanda klasifikasi regulasi (*Narkotika* / *Psikotropika*).
  4. `Satuan`: Unit sediaan fisik (Tablet, Ampul, Kapsul, Botol).
  5. `Stok Awal`: Saldo awal persediaan pada awal bulan periode terpilih.
  6. `Pemasukan`: Jumlah penerimaan obat dari supplier PBF resmi selama periode berjalan (+).
  7. `Pengeluaran`: Jumlah pemakaian/penyerahan obat melalui resep dokter selama periode berjalan (-).
  8. `Stok Akhir`: Saldo akhir fisik yang siap diaudit untuk pelaporan SIPNAP.
