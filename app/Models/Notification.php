<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'dev.notifications';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tipe',
        'pesan',
        'identifikasi_kebutuhan_id',
        'is_read',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
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