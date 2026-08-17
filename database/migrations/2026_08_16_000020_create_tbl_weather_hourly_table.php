<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_weather_hourly', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('barangay_name');
            $table->dateTime('forecast_datetime');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->unsignedTinyInteger('precipitation_probability')->nullable();
            $table->decimal('wind_speed', 6, 2)->nullable();
            $table->unsignedSmallInteger('weather_code')->nullable();
            $table->timestamps();

            $table->unique(
                ['barangay_name', 'forecast_datetime'],
                'weather_hourly_barangay_datetime_unique'
            );
            $table->index('barangay_name');
            $table->index('forecast_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_weather_hourly');
    }
};
