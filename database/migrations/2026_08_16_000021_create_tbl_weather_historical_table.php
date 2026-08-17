<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_weather_historical', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('barangay_name');
            $table->date('date');
            $table->decimal('precipitation_sum', 8, 2)->nullable();
            $table->decimal('temperature_max', 5, 2)->nullable();
            $table->decimal('et0_fao_evapotranspiration', 6, 3)->nullable();
            $table->timestamps();

            $table->unique(
                ['barangay_name', 'date'],
                'weather_historical_barangay_date_unique'
            );
            $table->index('barangay_name');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_weather_historical');
    }
};
