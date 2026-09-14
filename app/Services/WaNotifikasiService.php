<?php

namespace App\Services;

use App\Models\IdentifikasiKebutuhan;
use Illuminate\Support\Carbon;

/**
 * Penyusun pesan notifikasi WhatsApp — format FORMAL LENGKAP.
 *
 * Catatan penting:
 * - Notifikasi web (bell 🔔) tetap memakai teks PENDEK (lihat
 *   IdentifikasiKebutuhanController::pesanWeb()) karena dropdown-nya sempit.
 * - WA memakai pesan kaya dari service ini supaya penerima (Verifikator, Admin,
 *   atau PPK pembuat) langsung tahu konteks paket tanpa harus membuka aplikasi.
 *
 * Format WhatsApp: *tebal*, _miring_, `> kutipan`.
 *
 * Tata letak pesan (hasil penyempurnaan format):
 *   1. Kop SIKEBUT + garis pemisah
 *   2. Judul kejadian (ikon + nama peristiwa)
 *   3. IDENTITAS PAKET — baris bebas: Nama Paket & Sub Kegiatan. Sengaja di LUAR
 *      blok monospace karena teksnya bisa sangat panjang; di dalam blok ia akan
 *      melipat di layar HP lalu merusak grid kolom.
 *   4. RINCIAN SINGKAT — dibungkus ``` ``` (blok monospace) supaya titik dua
 *      benar-benar sejajar. Penting: WhatsApp memakai font PROPORSIONAL, jadi
 *      perataan memakai spasi biasa TIDAK akan sejajar. Di dalam blok kode,
 *      tanda bintang tidak dirender bold — karena itu isinya ditulis polos.
 *   5. Catatan verifikator (bila ada) + status + tautan aplikasi.
 */
class WaNotifikasiService
{
    /**
     * Ikon + judul + label status + kalimat penutup untuk tiap kejadian.
     *
     * @var array<string, array{ikon: string, judul: string, status: string, penutup: string}>
     */
    private const KEJADIAN = [
        'diajukan' => [
            'ikon' => '🔔',
            'judul' => 'PENGAJUAN PAKET BARU',
            'status' => 'MENUNGGU REVIEW',
            'penutup' => 'Paket baru menunggu verifikasi Anda. Silakan buka SIKEBUT untuk menindaklanjuti:',
        ],
        'disetujui' => [
            'ikon' => '✅',
            'judul' => 'PAKET DISETUJUI (FINAL)',
            'status' => 'DISETUJUI',
            'penutup' => 'Paket Anda telah disetujui sebagai usulan final. Silakan buka SIKEBUT untuk melihat detailnya:',
        ],
        'dikembalikan' => [
            'ikon' => '⚠️',
            'judul' => 'PAKET DIKEMBALIKAN UNTUK PERBAIKAN',
            'status' => 'PERLU PERBAIKAN',
            'penutup' => 'Mohon perbaiki paket sesuai catatan di atas, lalu ajukan kembali. Buka SIKEBUT:',
        ],
    ];

    /**
     * @var array<int, string>
     */
    private const BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    private const GARIS = '━━━━━━━━━━━━━━━━━━━━━━';

    /**
     * Susun pesan WA lengkap untuk satu paket.
     *
     * @param  string  $kejadian  diajukan | disetujui | dikembalikan
     * @param  string|null  $catatan  Catatan verifikator (dipakai saat disetujui/dikembalikan)
     */
    public static function pesanPaket(
        IdentifikasiKebutuhan $kebutuhan,
        string $kejadian,
        ?string $catatan = null
    ): string {
        $meta = self::KEJADIAN[$kejadian] ?? self::KEJADIAN['diajukan'];

        $kebutuhan->loadMissing(['skpd', 'subKegiatan', 'pembuat', 'anggaran']);

        // === Bagian identitas: baris bebas (boleh melipat, tidak perlu sejajar) ===
        // Nama Paket & Sub Kegiatan sengaja DI LUAR blok monospace: teksnya bisa
        // sangat panjang, dan di dalam blok ia akan melipat lalu merusak grid kolom.
        $kepala = ['*Paket :* '.((string) $kebutuhan->nama_paket ?: '-')];
        $subKegiatan = self::subKegiatan($kebutuhan);
        if ($subKegiatan !== '-') {
            $kepala[] = '*Sub Kegiatan :*';
            $kepala[] = $subKegiatan;
        }

        // === Bagian rincian: di dalam blok monospace supaya kolom benar-benar
        // sejajar di WhatsApp (font proporsional tidak bisa disejajarkan dgn spasi).
        // Di dalam ``` ``` tanda bintang TIDAK dirender bold, jadi tulis polos.
        $rincian = [
            'No. Paket' => '#'.$kebutuhan->id,
            'SKPD' => (string) ($kebutuhan->skpd?->nama_skpd ?: ($kebutuhan->kode_skpd ?: '-')),
            'Jenis' => (string) ($kebutuhan->jenis_pengadaan ?: '-'),
            'Cara' => (string) ($kebutuhan->cara_pengadaan ?: '-'),
            'Pagu Paket' => self::rupiah((float) $kebutuhan->anggaran->sum('pagu')),
            'Tahun' => (string) ($kebutuhan->tahun ?: date('Y')),
            'Pengaju' => (string) ($kebutuhan->pembuat?->nama ?: '-'),
            'Waktu' => self::waktu($kebutuhan->updated_at),
        ];

        // +1 supaya label terpanjang pun tetap punya SATU spasi sebelum titik dua
        // (kalau tanpa +1, label terpanjang dapat spasi ganda dan titik duanya bergeser).
        $lebarLabel = 1;
        foreach (array_keys($rincian) as $label) {
            $lebarLabel = max($lebarLabel, mb_strlen($label) + 1);
        }

        $baris = [];
        foreach ($rincian as $label => $nilai) {
            $spasi = str_repeat(' ', $lebarLabel - mb_strlen($label));
            $baris[] = $label.$spasi.': '.$nilai;
        }

        $pesan = [
            '*SIKEBUT — IDENTIFIKASI KEBUTUHAN PENGADAAN*',
            '*BIRO PENGADAAN BARANG/JASA — PROVINSI SUMATERA BARAT*',
            self::GARIS,
            $meta['ikon'].' *'.$meta['judul'].'*',
            self::GARIS,
            implode("\n", $kepala),
            '',
            "```\n".implode("\n", $baris)."\n```",
        ];

        // Catatan verifikator — hanya relevan saat disetujui / dikembalikan.
        $catatan = $catatan !== null ? trim($catatan) : '';
        if ($catatan !== '' && in_array($kejadian, ['disetujui', 'dikembalikan'], true)) {
            $pesan[] = '';
            $pesan[] = '*Catatan Verifikator:*';
            foreach (preg_split('/\r\n|\r|\n/', $catatan) ?: [] as $barisCatatan) {
                $pesan[] = '> '.$barisCatatan;
            }
        }

        $pesan[] = '';
        $pesan[] = '*STATUS: '.$meta['status'].'*';
        $pesan[] = self::GARIS;
        $pesan[] = $meta['penutup'];
        $pesan[] = self::tautan();

        return implode("\n", $pesan);
    }

    /**
     * Tautan langsung ke daftar usulan di aplikasi (dari FRONTEND_URL).
     */
    public static function tautan(): string
    {
        $base = (string) config('app.frontend_url', 'http://localhost:3000');

        return rtrim($base, '/').'/dashboard/identifikasi/data';
    }

    /**
     * "KODE — Nama Sub Kegiatan" (nama saja bila kode kosong).
     */
    private static function subKegiatan(IdentifikasiKebutuhan $kebutuhan): string
    {
        $kode = trim((string) $kebutuhan->kode_sub_kegiatan);
        $nama = trim((string) ($kebutuhan->subKegiatan?->nama_sub_kegiatan ?? ''));

        if ($kode === '' && $nama === '') {
            return '-';
        }

        return $nama !== '' ? ($kode !== '' ? $kode.' — '.$nama : $nama) : $kode;
    }

    private static function rupiah(float $angka): string
    {
        return 'Rp '.number_format($angka, 0, ',', '.');
    }

    /**
     * Tanggal Indonesia: "11 September 2026, 14:30".
     */
    private static function waktu(mixed $tanggal): string
    {
        $dt = $tanggal ? Carbon::parse($tanggal) : Carbon::now();
        $bulan = self::BULAN[(int) $dt->format('n')] ?? $dt->format('F');

        return $dt->format('d').' '.$bulan.' '.$dt->format('Y').', '.$dt->format('H:i');
    }
}
