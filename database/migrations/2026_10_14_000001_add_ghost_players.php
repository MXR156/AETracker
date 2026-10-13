<?php

use App\Models\Player;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Anonymous" players (deleted accounts or no name set) can't be told apart, so each one
     * becomes a ghost player that belongs to a single location: "Anonymous #1", "Anonymous #2", ...
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('username')->constrained()->cascadeOnDelete();
            $table->dropUnique(['username']);
            $table->unique(['username', 'location_id']);
        });

        // Split any existing shared "Anonymous" player into per-location ghosts.
        DB::transaction(function () {
            $anonymous = DB::table('players')->whereNull('location_id')->get()
                ->filter(fn ($p) => Player::isGhostName($p->username));

            foreach ($anonymous as $old) {
                $entries = DB::table('snapshot_entries')
                    ->join('snapshots', 'snapshots.id', '=', 'snapshot_entries.snapshot_id')
                    ->where('snapshot_entries.player_id', $old->id)
                    ->select('snapshot_entries.id', 'snapshots.location_id')
                    ->get();

                foreach ($entries as $entry) {
                    $ghost = Player::ghost($entry->location_id, Player::ghostNumber($old->username) ?? 1);
                    DB::table('snapshot_entries')->where('id', $entry->id)->update(['player_id' => $ghost->id]);
                }
                DB::table('players')->where('id', $old->id)->delete();
            }
        });
    }

    public function down(): void
    {
        DB::table('players')->whereNotNull('location_id')->delete();

        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique(['username', 'location_id']);
            $table->dropConstrainedForeignId('location_id');
            $table->unique('username');
        });
    }
};
