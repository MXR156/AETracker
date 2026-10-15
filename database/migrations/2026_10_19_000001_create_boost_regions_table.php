<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boost and badge tiers differ by country, so admins keep a set per country and each account
     * picks theirs. The tiers saved so far become "United Kingdom", the default.
     */
    public function up(): void
    {
        Schema::create('boost_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('boost_tiers');
            $table->text('badge_tiers')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $saved = DB::table('settings')->whereIn('key', ['boost_tiers', 'badge_tiers'])->pluck('value', 'key');
        DB::table('boost_regions')->insert([
            'name' => 'United Kingdom',
            'boost_tiers' => $saved['boost_tiers'] ?? config('aetracker.defaults.boost_tiers'),
            'badge_tiers' => $saved['badge_tiers'] ?? config('aetracker.defaults.badge_tiers'),
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('settings')->whereIn('key', ['boost_tiers', 'badge_tiers'])->delete();
    }

    public function down(): void
    {
        $default = DB::table('boost_regions')->where('is_default', true)->first();
        if ($default) {
            DB::table('settings')->insert([
                ['key' => 'boost_tiers', 'value' => $default->boost_tiers],
                ['key' => 'badge_tiers', 'value' => $default->badge_tiers],
            ]);
        }
        DB::table('user_settings')->where('key', 'boost_region_id')->delete();
        Schema::dropIfExists('boost_regions');
    }
};
