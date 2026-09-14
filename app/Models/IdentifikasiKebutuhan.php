<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentifikasiKebutuhan extends Model
{
    use HasFactory;

    protected $table = 'dev.identifikasi_kebutuhan';

    protected $fillable = [
        'user_id',
        'tahun',
        'kode_klpd',
        'kode_skpd',
        'kode_program',
        'kode_kegiatan',
        'kode_sub_kegiatan',
        'cara_pengadaan',
        'jenis_pengadaan',
        'nama_paket',
        'waktu_pemanfaatan_awal',
        'waktu_pemanfaatan_akhir',
        'waktu_pemilihan_awal',
        'waktu_pemilihan_akhir',
        'waktu_pelaksanaan_kontrak_awal',
        'waktu_pelaksanaan_kontrak_akhir',
        'waktu_pelaksanaan_pekerjaan_awal',
        'waktu_pelaksanaan_pekerjaan_akhir',
        'status_review',
        'form_data',
        'catatan_reviewer_detail',
        'catatan_reviewer',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'form_data' => 'array',
            'catatan_reviewer_detail' => 'array',
            'waktu_pemanfaatan_awal' => 'date',
            'waktu_pemanfaatan_akhir' => 'date',
            'waktu_pemilihan_awal' => 'date',
            'waktu_pemilihan_akhir' => 'date',
            'waktu_pelaksanaan_kontrak_awal' => 'date',
            'waktu_pelaksanaan_kontrak_akhir' => 'date',
            'waktu_pelaksanaan_pekerjaan_awal' => 'date',
            'waktu_pelaksanaan_pekerjaan_akhir' => 'date',
        ];
    }

    /**
     * @return HasMany<IdentifikasiKebutuhanAnggaran, $this>
     */
    public function anggaran(): HasMany
    {
        return $this->hasMany(IdentifikasiKebutuhanAnggaran::class, 'identifikasi_kebutuhan_id', 'id');
    }

    /**
     * Detail barang RKBMD terpilih (1 baris per barang per item Pagu Paket).
     *
     * @return HasMany<IdentifikasiKebutuhanRkbmd, $this>
     */
    public function rkbmdItems(): HasMany
    {
        return $this->hasMany(IdentifikasiKebutuhanRkbmd::class, 'identifikasi_kebutuhan_id', 'id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return BelongsTo<RefSkpd, $this>
     */
    public function skpd(): BelongsTo
    {
        return $this->belongsTo(RefSkpd::class, 'kode_skpd', 'kode_skpd');
    }

    /**
     * @return BelongsTo<RefProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(RefProgram::class, 'kode_program', 'kode_program');
    }

    /**
     * @return BelongsTo<RefKegiatan, $this>
     */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(RefKegiatan::class, 'kode_kegiatan', 'kode_kegiatan');
    }

    /**
     * @return BelongsTo<RefSubKegiatan, $this>
     */
    public function subKegiatan(): BelongsTo
    {
        return $this->belongsTo(RefSubKegiatan::class, 'kode_sub_kegiatan', 'kode_sub_kegiatan');
    }

    /**
     * Riwayat / audit trail perubahan status paket.
     *
     * @return HasMany<IdentifikasiKebutuhanRiwayat, $this>
     */
    public function riwayat(): HasMany
    {
        return $this->hasMany(IdentifikasiKebutuhanRiwayat::class, 'identifikasi_kebutuhan_id', 'id')
            ->latest('created_at');
    }
}
