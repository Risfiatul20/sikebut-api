<?php

namespace App\Http\Requests\IdentifikasiKebutuhan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIdentifikasiKebutuhanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_paket' => ['sometimes', 'required', 'string', 'max:255'],
            'cara_pengadaan' => ['sometimes', 'required', 'string', 'max:50'],
            'jenis_pengadaan' => ['sometimes', 'nullable', 'string', 'max:50'],
            'kode_skpd' => ['sometimes', 'required', 'string', 'max:50', Rule::exists('pgsql.dev.ref_skpd', 'kode_skpd')],
            'kode_klpd' => ['sometimes', 'nullable', 'string', 'max:50'],
            'kode_program' => ['sometimes', 'nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_program', 'kode_program')],
            'kode_kegiatan' => ['sometimes', 'nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_kegiatan', 'kode_kegiatan')],
            'kode_sub_kegiatan' => ['sometimes', 'nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_sub_kegiatan', 'kode_sub_kegiatan')],
            'status_review' => ['sometimes', 'nullable', 'string', 'max:50'],
            'waktu_pemanfaatan_awal' => ['sometimes', 'nullable', 'date'],
            'waktu_pemanfaatan_akhir' => ['sometimes', 'nullable', 'date', 'after_or_equal:waktu_pemanfaatan_awal'],
            'waktu_pemilihan_awal' => ['sometimes', 'nullable', 'date'],
            'waktu_pemilihan_akhir' => ['sometimes', 'nullable', 'date', 'after_or_equal:waktu_pemilihan_awal'],
            'waktu_pelaksanaan_kontrak_awal' => ['sometimes', 'nullable', 'date'],
            'waktu_pelaksanaan_kontrak_akhir' => ['sometimes', 'nullable', 'date', 'after_or_equal:waktu_pelaksanaan_kontrak_awal'],
            'waktu_pelaksanaan_pekerjaan_awal' => ['sometimes', 'nullable', 'date'],
            'waktu_pelaksanaan_pekerjaan_akhir' => ['sometimes', 'nullable', 'date', 'after_or_equal:waktu_pelaksanaan_pekerjaan_awal'],
            'form_data' => ['sometimes', 'required', 'array'],
            'catatan_reviewer' => ['sometimes', 'nullable', 'string'],
            'catatan_reviewer_detail' => ['sometimes', 'nullable', 'array'],
            'anggaran' => ['sometimes', 'present', 'array', 'min:1'],
            'anggaran.*.id' => ['nullable', 'integer'],
            'anggaran.*.id_sipd_penetapan' => ['required', 'integer', Rule::exists('pgsql.dev.sipd_penetapan_apbd', 'id')],
            'anggaran.*.kode_standar_harga' => ['nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_standar_harga', 'kode_standar_harga')],
            'anggaran.*.pagu' => ['required', 'numeric', 'min:0'],
            'anggaran.*.perubahan_standar' => ['nullable', 'array'],        ];
    }
}
