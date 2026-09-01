<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RefSubKegiatan extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_sub_kegiatan';

    protected $primaryKey = 'kode_sub_kegiatan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_sub_kegiatan',
        'kode_kegiatan',
        'nama_sub_kegiatan',
    ];

    /**
     * @return BelongsTo<RefKegiatan, $this>
     */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(RefKegiatan::class, 'kode_kegiatan', 'kode_kegiatan');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'dev.user_sub_kegiatan',
            'kode_sub_kegiatan',
            'user_id',
            'kode_sub_kegiatan',
            'id'
        )->withPivot('created_at');
    }
}
