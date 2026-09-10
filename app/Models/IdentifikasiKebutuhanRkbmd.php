<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail barang RKBMD yang dipilih pada sebuah identifikasi kebutuhan.
 * Satu identifikasi → banyak baris (1 baris per barang terpilih per item Pagu Paket).
 * `id_pengadaan` menampung id RKBMD (id_pengadaan untuk jenis pengadaan,
 * id_pemeliharaan untuk jenis pemeliharaan) — dibedakan oleh kolom `jenis_rkbmd`.
 */
class IdentifikasiKebutuhanRkbmd extends Model
{
    use HasFactory;

    protected $table = 'dev.identifikasi_kebutuhan_rkbmd';

    public $timestamps = false;

    protected $fillable = [
        'identifikasi_kebutuhan_id',
        'kode_standar',
        'kode_rekening',
        'id_pengadaan',
        'jenis_rkbmd',
        'jumlah',
    ];

    protected function casts(): array
    {
        return [
            'id_pengadaan' => 'integer',
            'jumlah' => 'integer',
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
}