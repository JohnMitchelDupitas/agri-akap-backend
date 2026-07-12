<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crop_monitorings', function (Blueprint $table) {
            $table->decimal('area_planted_ha', 8, 2)->nullable()->after('season');
            $table->decimal('expected_yield_kg', 12, 2)->nullable()->after('area_planted_ha');
            $table->decimal('actual_yield_kg', 12, 2)->nullable()->after('expected_yield_kg');
            $table->string('crop_stage')->nullable()->after('actual_yield_kg'); // e.g. Seedling, Vegetative, Reproductive, Harvest
        });
    }

    public function down(): void
    {
        Schema::table('crop_monitorings', function (Blueprint $table) {
            $table->dropColumn(['area_planted_ha', 'expected_yield_kg', 'actual_yield_kg', 'crop_stage']);
        });
    }
};
