<?php

namespace App\Http\Resources;

use App\Models\IdentifikasiKebutuhan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IdentifikasiKebutuhan
 */
class IdentifikasiKebutuhanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'pembuat' => $this->whenLoaded('pembuat', fn () => [
                'id' => $this->pembuat?->id,
                'nama' => $this->pembuat?->nama,
                'username' => $this->pembuat?->username,
            ]),
            'nama_paket' => $this->nama_paket,
            'cara_pengadaan' => $this->cara_pengadaan,
            'jenis_pengadaan' => $this->jenis_pengadaan,
            'status_review' => $this->status_review,
            'kode_klpd' => $this->kode_klpd,
            'kode_skpd' => $this->kode_skpd,
            'nama_skpd' => $this->whenLoaded('skpd', fn () => $this->skpd?->nama_skpd),
            'kode_program' => $this->kode_program,
            'nama_program' => $this->whenLoaded('program', fn () => $this->program?->nama_program),
            'kode_kegiatan' => $this->kode_kegiatan,
            'nama_kegiatan' => $this->whenLoaded('kegiatan', fn () => $this->kegiatan?->nama_kegiatan),
            'kode_sub_kegiatan' => $this->kode_sub_kegiatan,
            'nama_sub_kegiatan' => $this->whenLoaded('subKegiatan', fn () => $this->subKegiatan?->nama_sub_kegiatan),
            'waktu_pemanfaatan_awal' => $this->waktu_pemanfaatan_awal?->toDateString(),
            'waktu_pemanfaatan_akhir' => $this->waktu_pemanfaatan_akhir?->toDateString(),
            'waktu_pemilihan_awal' => $this->waktu_pemilihan_awal?->toDateString(),
            'waktu_pemilihan_akhir' => $this->waktu_pemilihan_akhir?->toDateString(),
            'waktu_pelaksanaan_kontrak_awal' => $this->waktu_pelaksanaan_kontrak_awal?->toDateString(),
            'waktu_pelaksanaan_kontrak_akhir' => $this->waktu_pelaksanaan_kontrak_akhir?->toDateString(),
            'waktu_pelaksanaan_pekerjaan_awal' => $this->waktu_pelaksanaan_pekerjaan_awal?->toDateString(),
            'waktu_pelaksanaan_pekerjaan_akhir' => $this->waktu_pelaksanaan_pekerjaan_akhir?->toDateString(),
            'form_data' => $this->form_data,
            'catatan_reviewer' => $this->catatan_reviewer,
            'catatan_reviewer_detail' => $this->catatan_reviewer_detail,
            'total_pagu' => $this->whenLoaded('anggaran', fn () => number_format((float) $this->anggaran->sum('pagu'), 2, '.', '')),
            'jumlah_anggaran' => $this->whenLoaded('anggaran', fn () => $this->anggaran->count()),
            'anggaran' => IdentifikasiKebutuhanAnggaranResource::collection($this->whenLoaded('anggaran')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
