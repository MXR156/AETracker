<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Hands personal data that doesn't belong to any account yet (from before logins were per user,
 * or copied in from an older database) to an account: parcel counts, personal settings
 * (badges, projection) and the "me" player.
 */
class AccountAdoption
{
    public const LEGACY_ME = 'legacy_me_player_id';

    public static function hasOrphans(): bool
    {
        return DB::table('holdings')->whereNull('user_id')->exists()
            || DB::table('settings')->whereIn('key', array_merge(config('aetracker.page_settings'), [self::LEGACY_ME]))->exists();
    }

    public static function adopt(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            DB::table('holdings')->whereNull('user_id')->update(['user_id' => $userId]);

            $personal = DB::table('settings')->whereIn('key', config('aetracker.page_settings'))->get();
            foreach ($personal as $setting) {
                DB::table('user_settings')->updateOrInsert(
                    ['user_id' => $userId, 'key' => $setting->key],
                    ['value' => $setting->value, 'updated_at' => now(), 'created_at' => now()],
                );
            }
            DB::table('settings')->whereIn('key', $personal->pluck('key'))->delete();

            $me = DB::table('settings')->where('key', self::LEGACY_ME)->value('value');
            if ($me && DB::table('players')->where('id', $me)->exists()
                && ! DB::table('users')->where('player_id', $me)->exists()
                && ! DB::table('users')->where('id', $userId)->value('player_id')) {
                DB::table('users')->where('id', $userId)->update(['player_id' => $me]);
            }
            DB::table('settings')->where('key', self::LEGACY_ME)->delete();
        });
    }
}
