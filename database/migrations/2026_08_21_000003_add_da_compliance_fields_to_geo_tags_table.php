<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DA GeoLogBook Compliance: adds the DA-RSBSA planting calendar window and the
 * digital signature pair (farmer consent + AEW/technician validation) that
 * replace the paper KoboCollect georeferencing form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_tags', function (Blueprint $table) {
            $table->string('planting_start_month', 20)->nullable()->after('crop_variety');
            $table->string('planting_end_month', 20)->nullable()->after('planting_start_month');
            $table->string('farmer_signature_path')->nullable()->after('photo_path');
            $table->string('aew_signature_path')->nullable()->after('farmer_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('geo_tags', function (Blueprint $table) {
            $table->dropColumn([
                'planting_start_month',
                'planting_end_month',
                'farmer_signature_path',
                'aew_signature_path',
            ]);
        });
    }
};
