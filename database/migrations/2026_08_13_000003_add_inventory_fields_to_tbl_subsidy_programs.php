<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_subsidy_programs', function (Blueprint $table) {
            $table->string('unit_of_measurement')->default('Bags')->after('items_per_hectare');
            $table->unsignedInteger('total_quantity')->default(0)->after('unit_of_measurement');
            $table->unsignedInteger('remaining_quantity')->default(0)->after('total_quantity');
            $table->unsignedInteger('reorder_level')->nullable()->after('remaining_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_subsidy_programs', function (Blueprint $table) {
            $table->dropColumn(['unit_of_measurement', 'total_quantity', 'remaining_quantity', 'reorder_level']);
        });
    }
};
