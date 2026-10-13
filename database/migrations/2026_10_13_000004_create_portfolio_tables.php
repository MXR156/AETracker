<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A dated record of how many parcels of each rarity I own. The latest row is "now".
        Schema::create('holdings', function (Blueprint $table) {
            $table->id();
            $table->date('recorded_on');
            $table->unsignedInteger('common')->default(0);
            $table->unsignedInteger('rare')->default(0);
            $table->unsignedInteger('epic')->default(0);
            $table->unsignedInteger('legendary')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('boost_percent', 8, 3)->default(0); // permanent rent boost this badge gives
            $table->decimal('cost', 10, 2)->nullable();         // real-money cost to buy it, if buyable
            $table->boolean('owned')->default(false);
            $table->date('acquired_on')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('holdings');
    }
};
