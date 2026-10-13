<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Holding extends Model
{
    protected $fillable = ['recorded_on', 'common', 'rare', 'epic', 'legendary', 'notes'];

    /** Stored as a plain Y-m-d date so lookups by date behave the same on SQLite and MySQL. */
    protected function recordedOn(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public static function current(): ?self
    {
        return static::orderByDesc('recorded_on')->orderByDesc('id')->first();
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
