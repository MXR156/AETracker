<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One snapshot = the leaderboard of one location on one date.
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'recorded_on']);
        });

        Schema::create('snapshot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('parcels');
            $table->timestamps();

            $table->unique(['snapshot_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_entries');
        Schema::dropIfExists('snapshots');
    }
};
