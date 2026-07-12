<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the RSBSA parcel spatial reference (Georef ID / GPX ID) and the
     * landowner RSBSA number required when tenure is Tenant.
     */
    public function up(): void
    {
        Schema::table('farm_plots', function (Blueprint $table) {
            $table->string('georef_id')->nullable()->after('longitude');
            $table->string('land_owner_rsbsa_no')->nullable()->after('land_owner_ext_name');
        });
    }

    public function down(): void
    {
        Schema::table('farm_plots', function (Blueprint $table) {
            $table->dropColumn(['georef_id', 'land_owner_rsbsa_no']);
        });
    }
};
