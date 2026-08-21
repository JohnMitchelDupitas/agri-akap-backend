<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DA-RSBSA Georeferencing (RCM Protocol) compliance fields:
 * - boundary_points: full polygon vertex list captured by the mobile GIS walk
 * - non_productive_area_sqm: infrastructure/idle area subtracted from gross area
 * - has_discrepancy: technician-flagged spatial overlap / undeclared field
 * - georef_sms_sent_at: guards against duplicate Semaphore SMS receipts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_plots', function (Blueprint $table) {
            if (! Schema::hasColumn('farm_plots', 'boundary_points')) {
                $table->json('boundary_points')->nullable()->after('coordinates');
            }
            if (! Schema::hasColumn('farm_plots', 'non_productive_area_sqm')) {
                $table->decimal('non_productive_area_sqm', 12, 2)->default(0)->after('size_ha');
            }
            if (! Schema::hasColumn('farm_plots', 'has_discrepancy')) {
                $table->boolean('has_discrepancy')->default(false)->after('non_productive_area_sqm');
            }
            if (! Schema::hasColumn('farm_plots', 'georef_sms_sent_at')) {
                $table->timestamp('georef_sms_sent_at')->nullable()->after('has_discrepancy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('farm_plots', function (Blueprint $table) {
            $table->dropColumn(['boundary_points', 'non_productive_area_sqm', 'has_discrepancy', 'georef_sms_sent_at']);
        });
    }
};
