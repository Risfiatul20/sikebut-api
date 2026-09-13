<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentifikasiKebutuhanRiwayat extends Model
{
    use HasFactory;

    protected $table = 'dev.identifikasi_kebutuhan_riwayat';

    public $timestamps = false;

    protected $fillable = [
        'identifikasi_kebutuhan_id',
        'user_id',
        'status_dari',
        'status_ke',
        'catatan',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
