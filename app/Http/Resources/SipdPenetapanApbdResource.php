<?php

namespace App\Http\Resources;

use App\Models\SipdPenetapanApbd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SipdPenetapanApbd
 */
class SipdPenetapanApbdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_daerah' => $this->kode_daerah,
            'nama_daerah' => $this->nama_daerah,
            'tahun' => $this->tahun,
            'kode_sub_unit' => $this->kode_sub_unit,
            'nama_sub_unit' => $this->whenLoaded('subUnit', fn () => $this->subUnit?->nama_skpd),
            'kode_opd' => $this->whenLoaded('subUnit', fn () => $this->subUnit?->parent?->kode_skpd ?: $this->subUnit?->kode_skpd),
            'nama_opd' => $this->whenLoaded('subUnit', fn () => $this->subUnit?->parent?->nama_skpd ?: $this->subUnit?->nama_skpd),
            'kode_sub_kegiatan' => $this->kode_sub_kegiatan,
            'nama_sub_kegiatan' => $this->whenLoaded('subKegiatan', fn () => $this->subKegiatan?->nama_sub_kegiatan),
            'kode_kegiatan' => $this->whenLoaded('subKegiatan', fn () => $this->subKegiatan?->kegiatan?->kode_kegiatan),
            'nama_kegiatan' => $this->whenLoaded('subKegiatan', fn () => $this->subKegiatan?->kegiatan?->nama_kegiatan),
            'kode_program' => $this->whenLoaded('subKegiatan', fn () => $this->subKegiatan?->kegiatan?->program?->kode_program),
            'nama_program' => $this->whenLoaded('subKegiatan', fn () => $this->subKegiatan?->kegiatan?->program?->nama_program),
            'kode_standar_harga' => $this->kode_standar_harga,
            'nama_standar_harga' => $this->whenLoaded('standarHarga', fn () => $this->standarHarga?->nama_standar_harga),
            'kode_rekening' => $this->kode_rekening,
            'nama_rekening' => $this->whenLoaded('akun', fn () => $this->akun?->nama_akun),
            'kode_sumber_dana' => $this->kode_sumber_dana,
            'nama_sumber_dana' => $this->nama_sumber_dana,
            'pagu' => $this->pagu,
            'indikator_rkbmd' => $this->whenLoaded('akun', fn () => [
                'b' => (bool) $this->akun?->indikator?->is_belanja_pengadaan,
                'r' => (bool) $this->akun?->indikator?->is_rkbmd_pengadaan,
                'h' => (bool) $this->akun?->indikator?->is_rkbmd_pemeliharaan_rehab,
                't' => (bool) $this->akun?->indikator?->is_rkbmd_pemeliharaan_rutin,
            ]),
            'versi' => $this->versi,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
