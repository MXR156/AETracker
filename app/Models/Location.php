<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    protected $fillable = ['name', 'type', 'region', 'notes'];

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
}
