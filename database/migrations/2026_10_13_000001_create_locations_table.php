<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('village'); // village, town, city, county, state, country
            $table->string('region')->nullable();          // e.g. "Essex" - helps tell apart places with the same name
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['name', 'type', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
