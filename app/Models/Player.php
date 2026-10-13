<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    /** Names Atlas Earth shows for deleted or unnamed accounts, optionally numbered: "Anonymous", "Anon #2". */
    public const GHOST_PATTERN = '/^anon(?:ymous)?(?:\s*#?\s*(\d+))?$/i';

    protected $fillable = ['username', 'location_id', 'is_me', 'notes'];

    protected function casts(): array
    {
        return ['is_me' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SnapshotEntry::class);
    }

    /** Ghost players belong to one location; normal players have no location. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isGhost(): bool
    {
        return $this->location_id !== null;
    }

    public function scopeReal(Builder $query): void
    {
        $query->whereNull('location_id');
    }

    public static function isGhostName(string $name): bool
    {
        return (bool) preg_match(self::GHOST_PATTERN, trim($name));
    }

    /** The number in "Anonymous #3", or null when the name has none. */
    public static function ghostNumber(string $name): ?int
    {
        return preg_match(self::GHOST_PATTERN, trim($name), $m) && isset($m[1]) ? (int) $m[1] : null;
    }

    /** The numbered ghost player for a location, created if needed. */
    public static function ghost(Location|int $location, int $number): self
    {
        return static::firstOrCreate([
            'username' => 'Anonymous #'.$number,
            'location_id' => $location instanceof Location ? $location->id : $location,
        ]);
    }

    public static function me(): ?self
    {
        return static::where('is_me', true)->first();
    }

    /** Find a (non-ghost) player by username (case-insensitive) or create them. */
    public static function findOrCreateByName(string $username): self
    {
        $username = trim($username);

        return static::real()->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->first()
            ?? static::create(['username' => $username]);
    }
}
