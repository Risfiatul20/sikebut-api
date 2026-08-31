<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AkunIndikatorRkbmd extends Model
{
    use HasFactory;

    protected $table = 'dev.akun_indikator_rkbmd';

    protected $primaryKey = 'kode_akun';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_akun',
        'is_belanja_pengadaan',
        'is_rkbmd_pengadaan',
        'is_rkbmd_pemeliharaan_rehab',
        'is_rkbmd_pemeliharaan_rutin',
    ];

    protected function casts(): array
    {
        return [
            'is_belanja_pengadaan' => 'boolean',
            'is_rkbmd_pengadaan' => 'boolean',
            'is_rkbmd_pemeliharaan_rehab' => 'boolean',
            'is_rkbmd_pemeliharaan_rutin' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RefAkun, $this>
     */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(RefAkun::class, 'kode_akun', 'kode_akun');
    }
}
