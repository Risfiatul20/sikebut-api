<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessage extends Model
{
    use HasFactory;

    protected $table = 'dev.wa_messages';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'nomor_tujuan',
        'pesan',
        'status',
        'device_id',
        'error',
        'identifikasi_kebutuhan_id',
        'message_id',
        'sent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return BelongsTo<IdentifikasiKebutuhan, $this>
     */
    public function kebutuhan(): BelongsTo
    {
        return $this->belongsTo(IdentifikasiKebutuhan::class, 'identifikasi_kebutuhan_id', 'id');
    }
}