<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SipdPenetapanApbd extends Model
{
    use HasFactory;

    protected $table = 'dev.sipd_penetapan_apbd';

    public $timestamps = false;

    protected $fillable = [
        'kode_daerah',
        'nama_daerah',
        'tahun',
        'kode_sub_unit',
        'kode_sub_kegiatan',
        'kode_standar_harga',
        'kode_rekening',
        'kode_sumber_dana',
        'nama_sumber_dana',
        'pagu',
        'versi',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'pagu' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RefSkpd, $this>
     */
    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(RefSkpd::class, 'kode_sub_unit', 'kode_skpd');
    }

    /**
     * @return BelongsTo<RefSubKegiatan, $this>
     */
    public function subKegiatan(): BelongsTo
    {
        return $this->belongsTo(RefSubKegiatan::class, 'kode_sub_kegiatan', 'kode_sub_kegiatan');
    }

    /**
     * @return BelongsTo<RefStandarHarga, $this>
     */
    public function standarHarga(): BelongsTo
    {
        return $this->belongsTo(RefStandarHarga::class, 'kode_standar_harga', 'kode_standar_harga');
    }

    /**
     * Rekening on this row maps to the account reference (and its RKBMD indicators).
     *
     * @return BelongsTo<RefAkun, $this>
     */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(RefAkun::class, 'kode_rekening', 'kode_akun');
    }
}
