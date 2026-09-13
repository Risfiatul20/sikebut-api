<?php

namespace App\Http\Requests\IdentifikasiKebutuhan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentifikasiKebutuhanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi tanggal sebelum validasi: terima juga format "MM/YYYY" / "MM-YYYY"
     * (dikirim sebagian browser) → konversi ke "YYYY-MM-01" / akhir bulan.
     */
    protected function prepareForValidation(): void
    {
        $dateKeys = [
            'waktu_pemanfaatan_awal', 'waktu_pemanfaatan_akhir',
            'waktu_pemilihan_awal', 'waktu_pemilihan_akhir',
            'waktu_pelaksanaan_kontrak_awal', 'waktu_pelaksanaan_kontrak_akhir',
            'waktu_pelaksanaan_pekerjaan_awal', 'waktu_pelaksanaan_pekerjaan_akhir',
        ];
        $data = $this->all();
        foreach ($dateKeys as $key) {
            $val = $data[$key] ?? null;
            if (! is_string($val) || trim($val) === '') {
                continue;
            }
            $trimmed = trim($val);
            if (preg_match('/^(\d{1,2})[-\/](\d{4})$/', $trimmed, $m)) {
                $month = (int) $m[1];
                $year = (int) $m[2];
                if ($month >= 1 && $month <= 12 && $year >= 1900 && $year <= 2100) {
                    $isAkhir = str_ends_with($key, '_akhir');
                    $day = $isAkhir ? (int) date('t', mktime(0, 0, 0, $month, 1, $year)) : 1;
                    $data[$key] = sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }
        $this->merge($data);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_paket' => ['required', 'string', 'max:255'],
            'cara_pengadaan' => ['required', 'string', 'max:50'],
            'jenis_pengadaan' => ['nullable', 'string', 'max:50'],
            'kode_skpd' => ['required', 'string', 'max:50', Rule::exists('pgsql.dev.ref_skpd', 'kode_skpd')],
            'kode_klpd' => ['nullable', 'string', 'max:50'],
            'kode_program' => ['nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_program', 'kode_program')],
            'kode_kegiatan' => ['nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_kegiatan', 'kode_kegiatan')],
            'kode_sub_kegiatan' => ['nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_sub_kegiatan', 'kode_sub_kegiatan')],
            'tahun' => ['required', 'integer', 'digits:4'],
            'status_review' => ['nullable', 'string', 'max:50'],
            'waktu_pemanfaatan_awal' => ['nullable', 'date'],
            'waktu_pemanfaatan_akhir' => ['nullable', 'date', 'after_or_equal:waktu_pemanfaatan_awal'],
            'waktu_pemilihan_awal' => ['nullable', 'date'],
            'waktu_pemilihan_akhir' => ['nullable', 'date', 'after_or_equal:waktu_pemilihan_awal'],
            'waktu_pelaksanaan_kontrak_awal' => ['nullable', 'date'],
            'waktu_pelaksanaan_kontrak_akhir' => ['nullable', 'date', 'after_or_equal:waktu_pelaksanaan_kontrak_awal'],
            'waktu_pelaksanaan_pekerjaan_awal' => ['nullable', 'date'],
            'waktu_pelaksanaan_pekerjaan_akhir' => ['nullable', 'date', 'after_or_equal:waktu_pelaksanaan_pekerjaan_awal'],
            'form_data' => ['required', 'array'],
            'catatan_reviewer' => ['nullable', 'string'],
            'catatan_reviewer_detail' => ['nullable', 'array'],
            'anggaran' => ['required', 'array', 'min:1'],
            'anggaran.*.id_sipd_penetapan' => ['required', 'integer', Rule::exists('pgsql.dev.sipd_penetapan_apbd', 'id')],
            'anggaran.*.kode_standar_harga' => ['nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_standar_harga', 'kode_standar_harga')],
            'anggaran.*.pagu' => ['required', 'numeric', 'min:0'],
            'anggaran.*.perubahan_standar' => ['nullable', 'array'],
        ];
    }
}
