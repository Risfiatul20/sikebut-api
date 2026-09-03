<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPpk = strtoupper((string) $this->role) === 'PPK';

        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'username' => $this->username,
            'role' => $this->role,
            'kode_skpd' => $this->kode_skpd,
            'info' => $this->info,
            'created_at' => $this->created_at?->toISOString(),
            'skpd' => $this->whenLoaded('skpd', function () {
                return [
                    'kode_skpd' => $this->skpd?->kode_skpd,
                    'nama_skpd' => $this->skpd?->nama_skpd,
                    'parent_kode_skpd' => $this->skpd?->parent_kode_skpd,
                ];
            }),
            'sub_kegiatan' => $this->whenLoaded('subKegiatan', function () use ($isPpk) {
                return $this->subKegiatan->map(function ($item) use ($isPpk) {
                    $data = [
                        'kode_sub_kegiatan' => $item->kode_sub_kegiatan,
                        'kode_kegiatan' => $item->kode_kegiatan,
                        'nama_sub_kegiatan' => $item->nama_sub_kegiatan,
                    ];

                    if ($isPpk) {
                        $data['kegiatan'] = $item->relationLoaded('kegiatan') && $item->kegiatan ? [
                            'kode_kegiatan' => $item->kegiatan->kode_kegiatan,
                            'nama_kegiatan' => $item->kegiatan->nama_kegiatan,
                            'kode_program' => $item->kegiatan->kode_program,
                        ] : null;

                        $data['program'] = $item->relationLoaded('kegiatan') && $item->kegiatan?->relationLoaded('program') && $item->kegiatan?->program ? [
                            'kode_program' => $item->kegiatan->program->kode_program,
                            'kode_bidang_urusan' => $item->kegiatan->program->kode_bidang_urusan,
                            'nama_bidang_urusan' => $item->kegiatan->program->relationLoaded('bidangUrusan') ? $item->kegiatan->program->bidangUrusan?->nama_bidang_urusan : null,
                            'nama_program' => $item->kegiatan->program->nama_program,
                        ] : null;
                    }

                    return $data;
                });
            }),
            'programs' => $this->when($isPpk && $this->relationLoaded('subKegiatan'), function () {
                return $this->subKegiatan
                    ->map(fn ($item) => $item->relationLoaded('kegiatan') && $item->kegiatan?->relationLoaded('program') ? $item->kegiatan?->program : null)
                    ->filter()
                    ->unique('kode_program')
                    ->values()
                    ->map(fn ($program) => [
                        'kode_program' => $program->kode_program,
                        'kode_bidang_urusan' => $program->kode_bidang_urusan,
                        'nama_bidang_urusan' => $program->relationLoaded('bidangUrusan') ? $program->bidangUrusan?->nama_bidang_urusan : null,
                        'nama_program' => $program->nama_program,
                    ]);
            }),
            'kegiatans' => $this->when($isPpk && $this->relationLoaded('subKegiatan'), function () {
                return $this->subKegiatan
                    ->map(fn ($item) => $item->relationLoaded('kegiatan') ? $item->kegiatan : null)
                    ->filter()
                    ->unique('kode_kegiatan')
                    ->values()
                    ->map(fn ($kegiatan) => [
                        'kode_kegiatan' => $kegiatan->kode_kegiatan,
                        'kode_program' => $kegiatan->kode_program,
                        'nama_kegiatan' => $kegiatan->nama_kegiatan,
                    ]);
            }),
        ];
    }
}
