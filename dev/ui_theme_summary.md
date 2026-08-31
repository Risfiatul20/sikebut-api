# Ringkasan Tema & Gaya UI (UI Theme/Style Summary)

Proyek ini dirancang sebagai aplikasi formulir multi-langkah (wizard form) berskala administratif untuk keperluan **Identifikasi Kebutuhan Pemerintah Daerah** yang terintegrasi dengan **SIPD-RI**. Desain UI-nya mengusung gaya *high-density* (kepadatan tinggi), profesional, bersih, dan berorientasi pada visualisasi data tabular dan penginputan data administratif yang efisien.

Berikut adalah rincian tema dan gaya UI yang diimplementasikan:

---

## 1. Teknologi Stack UI & Framework
* **CSS Engine:** TailwindCSS v4 (menggunakan skema `@import "tailwindcss";` modern via `@tailwindcss/vite`).
* **Icons:** [Lucide React](https://lucide.dev/) untuk visualisasi representatif di setiap kategori formulir, tombol, dan status.
* **Animations:** Framer Motion (melalui pustaka `motion`) untuk transisi dinamis antar elemen.

---

## 2. Palet Warna (Color Palette)
Aplikasi ini menggunakan kombinasi warna netral berbasis abu-abu dingin (*cool grays*) dengan aksen warna fungsional yang kuat untuk penanda status:

| Kategori | Contoh Utility Class | Kegunaan |
| :--- | :--- | :--- |
| **Neutral Backgrounds** | `bg-slate-100`, `bg-slate-50/50`, `bg-white` | Latar belakang aplikasi, container utama, panel, dan kartu formulir. |
| **Neutral Texts** | `text-slate-900`, `text-slate-800`, `text-slate-500`, `text-slate-400` | Hirarki teks dari judul utama, teks isi, label input, hingga teks pembantu (*helper text*). |
| **Primary / Active (Blue)** | `bg-blue-600`, `text-blue-900`, `bg-blue-50/90`, `border-blue-200` | Menandakan elemen aktif (langkah navigasi saat ini, tombol aksi utama, atau highlight penting). |
| **Success / Completed (Emerald)** | `bg-emerald-600`, `text-emerald-700`, `bg-emerald-50`, `border-emerald-200` | Menandakan langkah yang telah berhasil diisi/selesai, indikator koneksi aktif ke SIPD, serta preset tertentu. |
| **Alert / Accent (Amber/Orange)** | `bg-amber-500`, `text-amber-700`, `bg-amber-950/80`, `border-amber-200` | Menandakan status sinkronisasi data SIPD-RI, badge demo preset cepat (Barang, Konstruksi, Jasa Lainnya), dan *warning alerts*. |
| **Dynamic Specs (Indigo)** | `bg-indigo-500/20`, `text-indigo-300`, `bg-indigo-950/80` | Aksen warna khusus pada formulir dinamis spesifikasi kebutuhan (Tahap 3). |

---

## 3. Tipografi & Hirarki Visual
* **Font Family:** `font-sans` (menggunakan sistem font sans-serif modern bawaan).
* **Ukuran Teks Kompak:** Didominasi oleh ukuran teks yang kecil seperti `text-[10px]`, `text-[11px]`, `text-xs`, dan `text-sm`. Pendekatan ini dipilih agar formulir administratif dengan puluhan field input tetap muat dalam satu layar tanpa memerlukan scrolling berlebih (*high density interface*).
* **Monospace untuk Anggaran:** Angka nilai pagu anggaran dan kode rekening menggunakan kelas `font-mono` untuk mempermudah pembandingan data numerik secara vertikal.

---

## 4. Struktur Tata Letak (Layout) & Komponen
* **Sidebar Kiri (Aside):** Lebar tetap `w-64` (hingga `lg:w-72` pada layar besar) berwarna latar putih dengan pemisah garis abu-abu tipis (`border-slate-200`). Berisi identitas aplikasi di bagian atas, widget kemajuan pengisian form berupa persentase bar, serta daftar langkah navigasi (`StepNavigation`).
* **Header & Footer Kompak:** Memiliki tinggi tetap `h-14` dengan posisi melekat (*shrink-0*) untuk menjaga area form utama tetap maksimal.
  * **Header:** Menampilkan breadcrumbs dinamis "SIPD-RI > [OPD Aktif]", status koneksi SIPD, serta tombol kontrol preset demonstrasi (Barang, Konstruksi, Jasa Lainnya, Swakelola) dan Reset.
  * **Footer:** Menampilkan detail progres langkah ("Langkah X / 4") dan tombol navigasi aksi ("Sebelumnya" / "Langkah Selanjutnya" / "Kembali ke Awal").
* **Card Form & Kontainer:** Konten formulir berada di dalam kartu-kartu berlatar putih (`bg-white`) dengan sudut melengkung sedang (`rounded-lg`), garis tepi abu-abu halus (`border-slate-200`), dan bayangan sangat tipis (`shadow-2xs`).
* **Modal Dialog (Pagu RKA SIPD):** Menggunakan *overlay* gelap transparan dengan efek blur halus (`bg-slate-950/60 backdrop-blur-2xs`) untuk memusatkan fokus pengguna pada saat mencocokkan rekening belanja. Kepala modal bernuansa gelap (`bg-slate-900 text-white`).

---

## 5. Interaksi, Transisi & Feedback
* **Hover State:** Tombol memiliki efek transisi halus (`transition-colors`, `transition-all`, `duration-300`) saat disorot, beralih dari teks abu-abu ke warna primer aksen (seperti biru atau oranye).
* **Micro-Animations:**
  * **Pulse Indikator:** Langkah yang aktif pada sidebar memiliki dot biru yang berdenyut lambat (`animate-pulse`).
  * **Fade-in & Slide-in:** Modul langkah (`Step`) menggunakan animasi masuk cepat (`animate-in fade-in duration-150`).
  * **Toast Notification:** Toast meluncur ke bawah dari kanan atas dengan animasi `animate-in slide-in-from-top-4 duration-200` saat data tersimpan atau preset dimuat.
