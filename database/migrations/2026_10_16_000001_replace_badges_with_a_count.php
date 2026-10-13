<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Badges are now just a number ("badges_owned" setting), so keep the owned count and drop the list. */
    public function up(): void
    {
        $owned = DB::table('badges')->where('owned', true)->count();
        if ($owned > 0 && ! DB::table('settings')->where('key', 'badges_owned')->exists()) {
            DB::table('settings')->insert(['key' => 'badges_owned', 'value' => (string) $owned, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::dropIfExists('badges');
    }

    public function down(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('boost_percent', 8, 3)->default(0);
            $table->decimal('cost', 10, 2)->nullable();
            $table->boolean('owned')->default(false);
            $table->date('acquired_on')->nullable();
            $table->timestamps();
        });
    }
};
