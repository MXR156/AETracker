<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A country's ad boost and badge tiers. Admins keep them up to date; each account picks one. */
class BoostRegion extends Model
{
    protected $fillable = ['name', 'boost_tiers', 'badge_tiers', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::orderBy('id')->first();
    }

    /** The region an account uses: the one they picked, or the default. */
    public static function forUser(?User $user): ?self
    {
        $id = $user ? UserSetting::where('user_id', $user->id)->where('key', 'boost_region_id')->value('value') : null;

        return ($id ? static::find($id) : null) ?? static::default();
    }

    public function makeDefault(): void
    {
        static::where('is_default', true)->whereKeyNot($this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
