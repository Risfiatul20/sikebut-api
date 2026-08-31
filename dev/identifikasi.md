# PRODUCT REQUIREMENTS DOCUMENT (PRD)
**Aplikasi Identifikasi Kebutuhan Pengadaan (Biro PBJ) - Versi Pembaruan Modal**

---

## 1. Informasi Umum & Alur Logika

Dokumen ini adalah PRD untuk formulir dinamis **Identifikasi Kebutuhan Pengadaan**. Sistem memiliki dua cabang utama berdasarkan **Cara Pengadaan**: Penyedia atau Swakelola. Jika Penyedia dipilih, pengguna harus menentukan **Jenis Pengadaan** (Barang, Konstruksi, Jasa Lainnya, atau Konsultansi).

> **Penting:** Input "Pagu Paket" sekarang menggunakan Modal yang terintegrasi dengan RKA SIPD. Pengguna dapat memilih lebih dari 1 Standar Harga. Lokasi pada metode Swakelola dan Penyedia dapat diinput lebih dari 1 (multi-lokasi). Pilihan lokasi berjenang dari Provinsi (diambil dari https://wilayah.id/api/provinces.json), Kabupaten/Kota (https://wilayah.id/api/regencies/[PROVINCE_CODE].json), Kecamatan (https://wilayah.id/api/districts/[REGENCY_CODE].json). 


---

## 2. Struktur Data (Data Dictionary)

### 2.1. Identitas (Formulir Awal)
| Field | Tipe Data / Keterangan |
| :--- | :--- |
| Perangkat Daerah | Read-only (Default Akun) |
| Nama Sub Unit /SKPD | Read-only (Default Akun) |
| Nama KPA/PPK | Read-only (Default Akun) |
| Program | Dropdown (Sumber SIPD) |
| Kegiatan | Dropdown (Sumber SIPD) |
| Sub Kegiatan | Dropdown (Sumber SIPD) |
| Cara Pengadaan | Pilih (Penyedia / Swakelola) |

> Jika **Penyedia** -> Muncul field **Jenis Pengadaan**: Pilih (Barang / Jasa / Konsultansi / Konstruksi).

---

### 2.2. Formulir Kebutuhan Barang
*Kondisi: Cara Pengadaan = Penyedia & Jenis Pengadaan = Barang*

| Field | Input |
| :--- | :--- |
| Nama Paket, Fungsi/Kegunaan, Uraian, Spesifikasi | Text |
| Volume | Number + Pilih (Unit/Paket/Set) |
| PDN, Usaha Kecil, Pra DPA | Pilih (Ya/Tidak) |
| Sustainable Public Procurement (SPP) | Ekonomi (Ya/Tidak), Sosial (Ya/Tidak), Lingkungan (Ya/Tidak) |
| Lokasi | Dropdown: Prov, Kab/Kota, Kecamatan. Detail: Text. **(Bisa Tambah)** |
| Waktu Pemanfaatan | Awal (Bulan/Tahun), Akhir (Bulan/Tahun) |
| Perkiraan Waktu Pengadaan Barang | Pemilihan Penyedia (Awal-Akhir) & Pelaksanaan Kontrak (Awal-Akhir) |
| Metode Pengadaan | Pilih (ePurchasing / Tender / Pengadaan Langsung, dll) |
| Tersedia di e-Katalog LKPP | Pilih (Ya/Tidak) |
| **Pagu Paket** | **Modal berisi tampilan RKA SIPD (Bisa > 1 Standar Harga)** |
| Sumber Dana | Pilih (PAD / DAU / DAK / DBH / BLUD / dll) |
| **Identifikasi Barang Tersedia (Belanja Modal) & Pasokan** | |
| Jumlah Barang Yang dibutuhkan | Popup RKBMD Unit (Sumber RKBMD) |
| Jumlah barang sejenis tersedia/dimiliki | Number Unit (Sumber RKBMD) |
| Kondisi Barang | Baik, Rusak Ringan, Rusak Berat : Number Unit (Sumber RKBMD) |
| Kemudahan memperoleh di pasaran (sesuai jumlah) | Pilih (Ya/Tidak) |
| Produsen/pelaku usaha memenuhi syarat | Pilih (Banyak/Terbatas) |
| Kriteria Barang | Multi-select: PDN, Barang impor, Pabrikan, Produksi manual, Kerajinan tangan |
| Persyaratan nilai TKDN | Pilih (Ya/Tidak). Jika Ya -> Nilai TKDN: Number % |
| **Persyaratan Lain & Konsolidasi** | |
| Cara pengiriman, pengangkutan, pemasangan, penimbunan | Text |
| Cara pengoperasian/penggunaan | Pilih (Otomatis/manual) |
| Kebutuhan pelatihan pengoperasian/pemeliharaan | Pilih (Ya/Tidak) |
| Pengadaan Berkelanjutan (SPP) lanjutan | Multi-select: Aspek Ekonomi, Sosial, Lingkungan |
| Terdapat pengadaan sejenis pada kegiatan lain | Pilih (Ya/Tidak) |
| Indikasi konsolidasi atas pengadaan Barang | Pilih (Ya/Tidak) |

---

### 2.3. Formulir Kebutuhan Konstruksi
*Kondisi: Cara Pengadaan = Penyedia & Jenis Pengadaan = Konstruksi*

| Field | Input |
| :--- | :--- |
| Nama Paket, Fungsi, Target/sasaran, Uraian, Spesifikasi | Text |
| Volume, PDN, Usaha Kecil, Pra DPA | (Sama dengan form Barang) |
| SPP, Lokasi, Waktu Pemanfaatan, Perkiraan Waktu | (Sama dengan form Barang) |
| Tersedia di e-Katalog LKPP? (Produk & Material) | 2x Pilih (Ya/Tidak) |
| Penggunaan barang/material | Dalam Negeri: Number % / Luar Negeri: Number % |
| Tingkat prioritas kebutuhan & Studi kelayakan | Pilih (Tinggi/Sedang/Kecil) |
| Penyusunan dokumen DED | Pilih (Sudah/Belum dilakukan) |
| Kompleksitas pekerjaan | Pilih (Kompleks/Sederhana). *Catatan DED max 1 thn sblmnya jika kompleks.* |
| Kontrak tahun jamak (multi years) | Pilih (Ya/Tidak). Jika Ya -> Jumlah tahun pelaksanaan: Number tahun |
| Izin kontrak tahun jamak | Pilih (Ya/Tidak). Jika Ya -> Nomor surat izin: Text |
| Dapat dilaksanakan oleh usaha kecil | Pilih (Ya/Tidak) |
| **Pagu Paket** & Sumber Dana | (Sama dengan form Barang) |
| **Pembebasan Lahan & Konsolidasi** | |
| Kebutuhan pembebasan lahan | Pilih (Ya/Tidak). Jika Ya -> Luas lahan: Number m2 |
| Kebutuhan izin pemanfaatan tanah (termasuk akses) | Pilih (Ya/Tidak) |
| Lama waktu pembebasan/pengurusan izin | Number bulan |
| Administrasi pembayaran ganti rugi | Pilih (Selesai/Belum) |
| Identifikasi konstruksi tersedia (RKBMD) | Popup & Number Unit (Sama dengan form Barang) |
| Konsolidasi pekerjaan konstruksi (Terdapat sejenis & Indikasi) | 2x Pilih (Ya/Tidak) |

---

### 2.4. Formulir Kebutuhan Jasa Lainnya
*Kondisi: Cara Pengadaan = Penyedia & Jenis Pengadaan = Jasa Lainnya*

| Field | Input |
| :--- | :--- |
| Nama Paket, Kebutuhan Rutin Tahunan, Fungsi/Kegunaan | Text, Pilih (Ya/Tidak) |
| Kompetensi/Spesifikasi Teknis, Sumberdaya dimiliki | Text |
| Jumlah pelaku usaha memenuhi syarat | Pilih (Banyak/Terbatas) |
| Uraian, Spesifikasi, Volume, PDN, Usaha Kecil, SPP, Pra DPA | (Sama dengan form Barang) |
| Lokasi, Waktu Pemanfaatan, Perkiraan Waktu, Metode, e-Katalog | (Sama dengan form Barang) |
| **Pagu Paket**, Sumber Dana, Konsolidasi | (Sama dengan form Konstruksi) |

---

### 2.5. Formulir Kebutuhan Jasa Konsultansi
*Kondisi: Cara Pengadaan = Penyedia & Jenis Pengadaan = Konsultansi*

| Field | Input |
| :--- | :--- |
| Nama Paket, Target/Sasaran, Uraian, Spesifikasi | Text |
| Jenis Penyedia | Pilih (Perorangan/Badan Usaha) |
| Jumlah pelaku usaha memenuhi syarat | Pilih (Banyak/Terbatas) |
| Volume, PDN, Usaha Kecil, SPP, Pra DPA | (Sama dengan form Barang) |
| Lokasi, Waktu Pemanfaatan, Perkiraan Waktu, e-Katalog | (Sama dengan form Barang) |
| Metode Pengadaan | Pilih (Seleksi/Pengadaan Langsung, dll) |
| **Pagu Paket**, Sumber Dana, Konsolidasi | (Sama dengan form Barang) |

---

### 2.6. Formulir Kebutuhan Swakelola
*Kondisi: Cara Pengadaan = Swakelola*

| Field | Input |
| :--- | :--- |
| Nama Paket, Uraian Pekerjaan, Spesifikasi Pekerjaan | Text |
| Tipe Swakelola | Pilih (Tipe I/ Tipe II/ Tipe III/ Tipe IV) |
| Lokasi | Dropdown Prov, Kab/Kota, Kec. Detail: Text. **(Bisa > 1 lokasi)** |
| Waktu Pelaksanaan Pekerjaan | Awal (Bulan/Tahun), Akhir (Bulan/Tahun) |
| **Pagu Paket** | **Modal berisi tampilan RKA SIPD (Bisa > 1 Standar Harga)** |
| Sumber Dana | Pilih (APBD/BLUD/dll) |

---

## 3. Desain & Struktur Modal Pagu Paket (RKA SIPD)

Field "Pagu Paket" diseluruh form akan memunculkan komponen Modal UI dengan struktur data tabel (Grid) sebagai berikut:

### Bagian 1: Ringkasan Hierarki (OPD s/d Sub Kegiatan)
| Level | 1. Pagu | 2. Belanja Non Pengadaan | 3. Belanja Pengadaan | 4. Pagu Paket | 5. Sisa (4-5) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **OPD** | Total Pagu Standar Harga di OPD | Total Pagu Standar Harga di OPD | | Total Entri Pagu Kebutuhan di OPD | |
| **Sub Unit** | Total Pagu Standar Harga di Sub Unit | Total Pagu Standar Harga di Sub Unit | | Total Entri Pagu Kebutuhan di Sub Unit | |
| **Program** | Total Pagu Standar Harga di Program | Total Pagu Standar Harga di Program | | Total Entri Pagu Kebutuhan di Program | |
| **Kegiatan** | Total Pagu Standar Harga di Kegiatan | Total Pagu Standar Harga di Kegiatan | | Total Entri Pagu Kebutuhan di Kegiatan | |
| **Sub Kegiatan** | Total Pagu Standar Harga di Sub Kegiatan | Total Pagu Standar Harga di Sub Kegiatan | | Total Entri Pagu Kebutuhan di Sub Kegiatan | |

### Bagian 2: Detail Rekening & Standar Harga
| Rekening (1) | Nama Standar (2) | Belanja Pengadaan (3) | Belanja Telah Tagging (4) | Sisa (5 = 3-4-6) | Rencana Pagu Paket (6) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 5.2.03.01.001.00010 | Pembangunan Bangunan Gedung Negara Sederhana | Rp. 3.438.350.000 | Rp. 8.350.000 | Rp. 3.430.000.000 | **\* Inputan Pengguna** |
| ... | ... | ... | ... | ... | **\* Inputan Pengguna** |

> *\* Pengguna dapat memilih dan menginput Rencana Pagu Paket pada lebih dari 1 Standar Harga di dalam modal ini.*
