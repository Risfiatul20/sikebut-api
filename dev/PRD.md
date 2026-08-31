# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## 1. Informasi Umum

* **Nama Produk:** Sikebut (Sistem Informasi Identifikasi Kebutuhan)
* **Deskripsi:** Platform berbasis web untuk digitalisasi proses pra-perencanaan pengadaan di lingkungan Pemerintah Provinsi Sumatera Barat.
* **Target Pengguna:** Administrator, Kepala OPD, Kepala Sub Unit, Pejabat Pembuat Komitmen (PPK), dan Tim Verifikator (Biro PBJ).
* **Tujuan Utama:** Memastikan setiap usulan paket pengadaan selaras dengan pagu anggaran di SIPD, menyederhanakan *input* spesifikasi teknis pengadaan (Barang/Jasa/Konsultan/Konstruksi), serta mempercepat proses verifikasi.

---

## 2. Matriks Hak Akses (User Roles & Permissions)

Sistem akan menggunakan hierarki akses (Role-Based Access Control) dengan rincian wewenang sebagai berikut:

| Role | Wewenang dan Fitur Akses |
| --- | --- |
| **1. Admin** | • Mengelola akun pimpinan (Membuat akun Kepala OPD & Kepala Sub Unit).<br>

<br>• Melakukan *import* dan sinkronisasi master data dari SIPD dan RKBMD.<br>

<br>• Menetapkan **Periode Identifikasi Kebutuhan** (Tanggal Buka - Tutup). |
| **2. Kepala OPD** | • Membuat dan mengelola akun PPK di tingkat OPD.<br>

<br>• Memonitor (Read-Only) agregat dan progres *input* identifikasi kebutuhan dari seluruh PPK di OPD-nya. |
| **3. Kepala Sub Unit** | • Membuat akun PPK khusus untuk sub-unitnya.<br>

<br>• Memonitor (Read-Only) progres *input* identifikasi kebutuhan di lingkup sub-unitnya. |
| **4. PPK** | • Melakukan entri data identifikasi kebutuhan pengadaan (Penyedia / Swakelola).<br>

<br>• Menentukan alokasi pagu anggaran (merujuk data SIPD).<br>

<br>• Melakukan revisi berdasarkan catatan Tim Verifikator. |
| **5. Tim Verifikator** | • Memeriksa dan memverifikasi usulan dari seluruh PPK.<br>

<br>• Menolak, meminta revisi, atau menyetujui usulan.<br>

<br>• Memberikan **catatan perbaikan yang spesifik** (*field-level validation*). |

---

## 3. Aturan Bisnis (Business Rules) & Logika Sistem

* **Autentikasi (Authentication):** Pengguna masuk (login) menggunakan kombinasi *Username* dan *Password* bawaan sistem (tanpa mengandalkan sistem SSO eksternal).
* **Mekanisme Lock-out (Pembatasan Periode):** Akses entri bagi PPK sepenuhnya bergantung pada parameter waktu yang diatur oleh Admin. Ketika waktu sistem telah melewati batas akhir periode, tombol "Tambah/Edit Usulan" pada antarmuka PPK akan otomatis dinonaktifkan (terkunci).
* **Notifikasi In-App:** Segala bentuk pemberitahuan (seperti perubahan versi SIPD, catatan revisi dari verifikator, atau status persetujuan) dikelola secara eksklusif di dalam aplikasi (*in-app notification center* / *dashboard alert*). Tidak ada *blast* email atau WhatsApp.
* **Versioning Data Anggaran (SIPD):** Sistem melacak riwayat ID SIPD Penetapan. Jika Admin melakukan *import* SIPD versi terbaru dan ada pergeseran/perubahan nilai pada rekening yang sedang digunakan oleh PPK, aplikasi akan memunculkan peringatan kuning/merah agar PPK merevisi volume usulannya.

---

## 4. Fitur Utama & Fungsionalitas

### 4.1. Smart Dynamic Form (Multi-Step Wizard)

Formulir *input* dari PPK menggunakan UI bertahap agar tidak terjadi *cognitive overload*.

* **Step 1:** Hierarki data (Program -> Kegiatan -> Sub Kegiatan) dan penentuan Cara Pengadaan (Penyedia/Swakelola) serta Jenis Pengadaan (Barang/Konstruksi/dll).
* **Step 2:** Form dinamis (*conditional rendering*) yang menyesuaikan otomatis dengan spesifikasi isian di Step 1.
* **Step 3:** Penguncian pagu melalui Modal RKA SIPD. *Rule*: Total isian alokasi dari PPK tidak boleh melampaui "Sisa Pagu" pada rekening standar harga terkait.

### 4.2. Targeted Review System

Tim Verifikator dapat menyematkan catatan pada tingkat "kolom" (Misalnya, langsung mengarah ke "Tingkat Prioritas Konstruksi" atau "Nilai TKDN"). Catatan ini disimpan dalam format JSON (sebagai *Key-Value map*) sehingga antarmuka *frontend* dapat langsung menyorot (*highlight* merah) kotak *input* yang bermasalah bagi PPK.

### 4.3. Export Data Engine (Sesuai `Laporan.xlsx`)

Aplikasi Sikebut dilengkapi *generator* dokumen Excel mutakhir yang secara otomatis merekapitulasi data yang telah disetujui, langsung ke dalam lembar kerja (*sheet*) yang persis dengan *template* lampiran. Hasil ekspor memuat *sheet* berikut:

* `Rekap`
* `Penyedia`
* `Swakelola`
* `BA Pembahasan Penyedia` (Berita Acara)
* `BA Pembahasan Swakelola`
* `BA Catatan RKBMD Pengadaan`
* `BA Catatan RKBMD Pemeliharaan`

---

## 5. Arsitektur Teknis (High-Level Architecture)

### 5.1. Tech Stack

* **Frontend (UI/UX):** Next.js (App Router), React Hook Form (untuk *state* form kompleks), Tailwind CSS.
* **Backend (API & Logika Bisnis):** Laravel, mengandalkan *Job & Queue* untuk sinkronisasi ribuan baris data Excel SIPD dari Admin tanpa *timeout*.
* **Database:** PostgreSQL.

### 5.2. Skema Database Relasional + JSONB

Inti sistem dibangun menggunakan pola *Hybrid* (Relational & Document-based):

1. **Strict Relations:** Tabel Master (SKPD, User, SIPD, Standar Harga) direlasikan secara kaku (*Foreign Key*) untuk memastikan keakuratan pelacakan uang dan instansi.
2. **NoSQL Flexibility (`form_data`):** Entitas isian spesifik pengadaan (seperti metode pembebasan lahan, detail spek teknis, dan aspek SPP) diratakan (*flattened*) ke dalam satu kolom tipe **JSONB**. Hal ini mencegah pelebaran jumlah kolom tabel yang tidak efektif (terhindar dari banyaknya nilai `NULL`) ketika PPK mengisi form yang berbeda.
3. **Tabel Pivot Anggaran (`identifikasi_kebutuhan_anggaran`):** Menampung relasi *Many-to-Many* karena 1 Paket Pengadaan (Sikebut) bisa dibiayai oleh gabungan 2-3 standar harga / rekening SIPD yang berbeda.
