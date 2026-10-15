<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Settings. Game rules (rent rates, tiers...) are shared and live here; personal ones (badges,
 * projection - see config aetracker.page_settings) belong to each account, in user_settings.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function isPersonal(string $key): bool
    {
        return in_array($key, config('aetracker.page_settings'), true);
    }

    /** All settings for an account (the logged-in one by default), with defaults filled in. */
    public static function allValues(?User $user = null): array
    {
        $user ??= auth()->user();
        $shared = static::query()->whereNotIn('key', config('aetracker.page_settings'))->pluck('value', 'key')->all();
        $personal = $user ? UserSetting::where('user_id', $user->id)->pluck('value', 'key')->all() : [];

        $values = array_merge(config('aetracker.defaults'), $shared, $personal);

        // Boost and badge tiers come from the account's country (or the default one).
        if ($region = BoostRegion::forUser($user)) {
            $values['boost_tiers'] = $region->boost_tiers;
            $values['badge_tiers'] = $region->badge_tiers ?: $values['badge_tiers'];
            $values['boost_region_id'] = (string) $region->id;
            $values['boost_region_name'] = $region->name;
        }

        return $values;
    }

    public static function put(string $key, ?string $value, ?User $user = null): void
    {
        if (static::isPersonal($key)) {
            $user ??= auth()->user() ?? throw new RuntimeException("'{$key}' is a personal setting - it needs an account.");
            UserSetting::updateOrCreate(['user_id' => $user->id, 'key' => $key], ['value' => $value]);

            return;
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
