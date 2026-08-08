<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_weather_cache', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('forecast_date')->unique();
            $table->decimal('temperature_min', 5, 2)->nullable();
            $table->decimal('temperature_max', 5, 2)->nullable();
            $table->unsignedTinyInteger('precipitation_probability')->nullable();
            $table->decimal('soil_moisture', 6, 3)->nullable();
            $table->unsignedSmallInteger('weather_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_weather_cache');
    }
};
