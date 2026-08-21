<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FFRS 2.0 Protocol — Multi-Parcel & Planting Schedule Schema
 *
 * Aligns `tbl_farm_plots` with the official DA-RSBSA platform requirements:
 *   • parcel_name — human-readable label (e.g. "Parcel 1", "North Field")
 *   • planting_start_month — month the crop was/will be planted (e.g. "May")
 *   • planting_end_month — expected month of harvest (e.g. "October")
 *
 * Note: commodity already exists (added in 2026_05_27_234832_farm_plots).
 * Multiple plots per farmer are already supported via the farmer_id FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_plots', function (Blueprint $table) {
            $table->string('parcel_name', 100)->nullable()->after('farmer_id');
            $table->string('planting_start_month', 20)->nullable()->after('commodity');
            $table->string('planting_end_month', 20)->nullable()->after('planting_start_month');
        });
    }

    public function down(): void
    {
        Schema::table('farm_plots', function (Blueprint $table) {
            $table->dropColumn(['parcel_name', 'planting_start_month', 'planting_end_month']);
        });
    }
};
