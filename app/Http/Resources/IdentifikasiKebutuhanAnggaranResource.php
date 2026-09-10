<?php

namespace App\Http\Resources;

use App\Models\IdentifikasiKebutuhanAnggaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IdentifikasiKebutuhanAnggaran
 */
class IdentifikasiKebutuhanAnggaranResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifikasi_kebutuhan_id' => $this->identifikasi_kebutuhan_id,
            'id_sipd_penetapan' => $this->id_sipd_penetapan,
            'kode_standar_harga' => $this->kode_standar_harga,
            'nama_standar_harga' => $this->whenLoaded('standarHarga', fn () => $this->standarHarga?->nama_standar_harga),
            'pagu' => $this->pagu,
            'perubahan_standar' => $this->perubahan_standar,
            'sipd_penetapan' => $this->whenLoaded('sipdPenetapan', fn () => [
                'kode_sub_unit' => $this->sipdPenetapan?->kode_sub_unit,
                'kode_sub_kegiatan' => $this->sipdPenetapan?->kode_sub_kegiatan,
                'kode_rekening' => $this->sipdPenetapan?->kode_rekening,
                'kode_sumber_dana' => $this->sipdPenetapan?->kode_sumber_dana,
                'nama_sumber_dana' => $this->sipdPenetapan?->nama_sumber_dana,
                'tahun' => $this->sipdPenetapan?->tahun,
                'pagu_sipd' => $this->sipdPenetapan?->pagu,
                // 4 flag akun_indikator_rkbmd — acuan alur pertanyaan RKBMD per kode rekening
                // (juga dibutuhkan saat mode edit, karena frontend merekonstruksi item pagu dari data ini)
                'is_belanja_pengadaan' => (bool) ($this->sipdPenetapan?->akun?->indikator?->is_belanja_pengadaan ?? false),
                'is_rkbmd_pengadaan' => (bool) ($this->sipdPenetapan?->akun?->indikator?->is_rkbmd_pengadaan ?? false),
                'is_rkbmd_pemeliharaan_rehab' => (bool) ($this->sipdPenetapan?->akun?->indikator?->is_rkbmd_pemeliharaan_rehab ?? false),
                'is_rkbmd_pemeliharaan_rutin' => (bool) ($this->sipdPenetapan?->akun?->indikator?->is_rkbmd_pemeliharaan_rutin ?? false),
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
