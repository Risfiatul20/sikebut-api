<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RefAkun extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_akun';

    protected $primaryKey = 'kode_akun';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_akun',
        'nama_akun',
        'parent_kode_akun',
        'level_akun',
    ];

    protected function casts(): array
    {
        return [
            'level_akun' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RefAkun, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_kode_akun', 'kode_akun');
    }

    /**
     * @return HasMany<RefAkun, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_kode_akun', 'kode_akun');
    }

    /**
     * @return HasOne<AkunIndikatorRkbmd, $this>
     */
    public function indikator(): HasOne
    {
        return $this->hasOne(AkunIndikatorRkbmd::class, 'kode_akun', 'kode_akun');
    }
}
