<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Location extends Model
{
    protected $fillable = ['name', 'type', 'region', 'atlas_key', 'notes'];

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class)->orderBy('recorded_on');
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(Snapshot::class)->ofMany('recorded_on', 'max');
    }

    /** "Mayor", "Governor", ... depending on the location type. */
    public function leaderTitle(): string
    {
        return config('aetracker.location_types.'.$this->type, 'Mayor');
    }

    public function displayName(): string
    {
        return $this->region ? "{$this->name}, {$this->region}" : $this->name;
    }

    /** 1 = town/village/city, 2 = county/state, 3 = country. */
    public function level(): int
    {
        return match ($this->type) {
            'country' => 3,
            'county', 'state' => 2,
            default => 1,
        };
    }

    /**
     * The Atlas Earth country and county/state codes this location sits in (lower case), from
     * the leaderboard it was imported from, e.g. "city/eng/ess/basildon" -> eng, ess.
     *
     * @return array{country: ?string, state: ?string}
     */
    public function areaCodes(): array
    {
        $parts = $this->atlas_key ? explode('/', $this->atlas_key) : [];

        return match ($parts[0] ?? null) {
            'city', 'state' => ['country' => $parts[1] ?? null, 'state' => $parts[2] ?? null],
            'country' => ['country' => $parts[1] ?? null, 'state' => null],
            default => ['country' => null, 'state' => null],
        };
    }

    /**
     * Is $other inside this location - e.g. Basildon inside Essex, or Essex inside England?
     * Uses the Atlas Earth codes for imported places, and the Region field (code or name)
     * for ones added by hand.
     */
    public function contains(self $other): bool
    {
        if ($this->is($other) || $this->level() <= $other->level()) {
            return false;
        }
        $mine = $this->areaCodes();
        $theirs = $other->areaCodes();
        $slug = fn (?string $value) => filled($value) ? Str::slug($value) : null;

        if ($this->level() === 2) {
            $names = array_filter([$mine['state'], $slug($this->name)]);
            $theirState = $theirs['state'] ?? $slug($other->region);
            $sameCountry = ! $mine['country'] || ! $theirs['country'] || $mine['country'] === $theirs['country'];

            return $theirState && $sameCountry && in_array($theirState, $names, true);
        }

        $names = array_filter([$mine['country'], $slug($this->name)]);
        $theirCountry = $theirs['country'] ?? ($other->level() === 2 ? $slug($other->region) : null);

        return $theirCountry && in_array($theirCountry, $names, true);
    }

    /**
     * Of one player's positions (each with ->location), the ones to add up for their total: a
     * county's leaderboard already includes its towns, so a town is left out when the player is
     * also on a board that contains it.
     */
    public static function countedOnce(Collection $positions): Collection
    {
        return $positions->reject(fn ($p) => $positions->contains(fn ($other) => $other->location->contains($p->location)))->values();
    }
}
