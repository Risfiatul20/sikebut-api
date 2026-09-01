<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'dev.users';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kode_skpd',
        'nama',
        'username',
        'password',
        'role',
        'info',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'info' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RefSkpd, $this>
     */
    public function skpd(): BelongsTo
    {
        return $this->belongsTo(RefSkpd::class, 'kode_skpd', 'kode_skpd');
    }

    /**
     * @return BelongsToMany<RefSubKegiatan, $this>
     */
    public function subKegiatan(): BelongsToMany
    {
        return $this->belongsToMany(
            RefSubKegiatan::class,
            'dev.user_sub_kegiatan',
            'user_id',
            'kode_sub_kegiatan',
            'id',
            'kode_sub_kegiatan'
        )->withPivot('created_at');
    }

    /**
     * Sub kegiatan codes mapped to this user when role is PPK.
     * Returns null when the user should not be scoped.
     *
     * @return array<int, string>|null
     */
    public function ppkSubKegiatanCodes(): ?array
    {
        if (strtoupper((string) $this->role) !== 'PPK') {
            return null;
        }

        return DB::table('dev.user_sub_kegiatan')
            ->where('user_id', $this->id)
            ->pluck('kode_sub_kegiatan')
            ->all();
    }
}
