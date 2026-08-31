<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefSkpd extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_skpd';

    protected $primaryKey = 'kode_skpd';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_skpd',
        'nama_skpd',
        'parent_kode_skpd',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'kode_skpd', 'kode_skpd');
    }

    /**
     * @return BelongsTo<RefSkpd, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_kode_skpd', 'kode_skpd');
    }

    /**
     * @return HasMany<RefSkpd, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_kode_skpd', 'kode_skpd');
    }
}
