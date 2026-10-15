<?php

use App\Services\AccountAdoption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leaderboards stay shared, but each account gets its own "me" player, parcel counts and
     * personal settings (badges, projection). Existing personal data goes to the first admin -
     * or, if there are no accounts yet, waits for the first one to be created.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable()->unique()->after('is_admin')->constrained()->nullOnDelete();
        });

        Schema::table('holdings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'recorded_on']);
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });

        // Remember who was "me" before the column goes, so it can be handed to an account.
        $me = DB::table('players')->where('is_me', true)->value('id');
        if ($me) {
            DB::table('settings')->updateOrInsert(['key' => AccountAdoption::LEGACY_ME], ['value' => (string) $me]);
        }
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('is_me');
        });

        if ($admin = DB::table('users')->where('is_admin', true)->orderBy('id')->value('id')) {
            AccountAdoption::adopt($admin);
        }
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_me')->default(false);
        });
        foreach (DB::table('users')->whereNotNull('player_id')->orderBy('id')->limit(1)->get() as $user) {
            DB::table('players')->where('id', $user->player_id)->update(['is_me' => true]);
        }
        Schema::dropIfExists('user_settings');
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'recorded_on']);
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['player_id']);
            $table->dropConstrainedForeignId('player_id');
        });
    }
};
