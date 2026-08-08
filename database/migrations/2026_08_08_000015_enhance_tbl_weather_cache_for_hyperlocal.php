<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear municipal-only rows before switching to barangay-scoped cache.
        DB::table('tbl_weather_cache')->delete();

        Schema::table('tbl_weather_cache', function (Blueprint $table) {
            $table->dropUnique(['forecast_date']);

            $table->string('barangay_name')->after('id');
            $table->decimal('evapotranspiration', 8, 3)->nullable()->after('soil_moisture');
            $table->decimal('soil_moisture_28cm', 6, 3)->nullable()->after('evapotranspiration');
            $table->decimal('wind_speed_10m', 6, 2)->nullable()->after('soil_moisture_28cm');

            $table->unique(['barangay_name', 'forecast_date'], 'weather_cache_barangay_date_unique');
            $table->index('barangay_name');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_weather_cache', function (Blueprint $table) {
            $table->dropUnique('weather_cache_barangay_date_unique');
            $table->dropIndex(['barangay_name']);
            $table->dropColumn([
                'barangay_name',
                'evapotranspiration',
                'soil_moisture_28cm',
                'wind_speed_10m',
            ]);
            $table->unique('forecast_date');
        });
    }
};
