<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Snapshot extends Model
{
    protected $fillable = ['location_id', 'recorded_on', 'notes'];

    /** Stored as a plain Y-m-d date so lookups by date behave the same on SQLite and MySQL. */
    protected function recordedOn(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SnapshotEntry::class)->orderByDesc('parcels');
    }

    public function previous(): ?self
    {
        return static::where('location_id', $this->location_id)
            ->where('recorded_on', '<', $this->recorded_on->toDateString())
            ->orderByDesc('recorded_on')
            ->first();
    }
}
