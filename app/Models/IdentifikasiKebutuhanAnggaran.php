<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentifikasiKebutuhanAnggaran extends Model
{
    use HasFactory;

    protected $table = 'dev.identifikasi_kebutuhan_anggaran';

    public $timestamps = false;

    protected $fillable = [
        'identifikasi_kebutuhan_id',
        'id_sipd_penetapan',
        'kode_standar_harga',
        'pagu',
        'perubahan_standar',
    ];

    protected function casts(): array
    {
        return [
            'id_sipd_penetapan' => 'integer',
            'pagu' => 'decimal:2',
            'perubahan_standar' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<IdentifikasiKebutuhan, $this>
     */
    public function kebutuhan(): BelongsTo
    {
        return $this->belongsTo(IdentifikasiKebutuhan::class, 'identifikasi_kebutuhan_id', 'id');
    }

    /**
     * @return BelongsTo<RefStandarHarga, $this>
     */
    public function standarHarga(): BelongsTo
    {
        return $this->belongsTo(RefStandarHarga::class, 'kode_standar_harga', 'kode_standar_harga');
    }

    /**
     * @return BelongsTo<SipdPenetapanApbd, $this>
     */
    public function sipdPenetapan(): BelongsTo
    {
        return $this->belongsTo(SipdPenetapanApbd::class, 'id_sipd_penetapan', 'id');
    }
}
