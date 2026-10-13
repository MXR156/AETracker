<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = ['username', 'is_me', 'notes'];

    protected function casts(): array
    {
        return ['is_me' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SnapshotEntry::class);
    }

    public static function me(): ?self
    {
        return static::where('is_me', true)->first();
    }

    /** Find a player by username (case-insensitive) or create them. */
    public static function findOrCreateByName(string $username): self
    {
        $username = trim($username);

        return static::whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->first()
            ?? static::create(['username' => $username]);
    }
}
