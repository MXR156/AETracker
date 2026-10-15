<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Holding extends Model
{
    protected $fillable = ['user_id', 'recorded_on', 'common', 'rare', 'epic', 'legendary', 'notes'];

    /** Parcel counts are private: only ever look at one account's. */
    public function scopeFor(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', ($user ?? auth()->user())?->id);
    }

    /** Stored as a plain Y-m-d date so lookups by date behave the same on SQLite and MySQL. */
    protected function recordedOn(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /** An account's latest parcel counts (the logged-in account by default). */
    public static function current(?User $user = null): ?self
    {
        return static::for($user)->orderByDesc('recorded_on')->orderByDesc('id')->first();
    }

    public function total(): int
    {
        return $this->common + $this->rare + $this->epic + $this->legendary;
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        return $this->only(config('aetracker.rarities'));
    }
}
