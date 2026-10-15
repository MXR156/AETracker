<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'is_admin', 'player_id', 'last_login_at'];

    /** The AE player this account is ("me"). Private to the account. */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public static function admins(): int
    {
        return static::where('is_admin', true)->count();
    }
}
