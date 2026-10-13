<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remembers which Atlas Earth leaderboard a location was imported from (e.g. "city/eng/bdf/marston-moretaine"),
     * so later imports find it even if you rename the location.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('atlas_key')->nullable()->unique()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['atlas_key']);
            $table->dropColumn('atlas_key');
        });
    }
};
