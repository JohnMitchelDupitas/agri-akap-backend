<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g., "2026 Wet Season Hybrid Rice Seed Distribution"
            $table->text('description')->nullable();
            $table->enum('type', ['seeds', 'fertilizer', 'cash', 'equipment']);

            // Financial & Auditing tracking
            $table->decimal('budget_allocation', 15, 2)->nullable();
            $table->string('funding_source')->default('DA-RFO II'); // Dept of Agriculture Regional Field Office 2

            // Inventory Tracking Engine
            $table->integer('total_quantity');
            $table->integer('remaining_quantity');
            $table->string('unit_of_measurement')->default('bags'); // bags, pieces, liters, pesos

            // Automated Allocation Rules (Critical for Double-Dipping Prevention Math)
            $table->decimal('per_hectare_allocation', 10, 2)->default(1.00); // e.g., 2 bags per hectare
            $table->decimal('max_hectare_cap', 10, 2)->default(3.00); // e.g., max subsidy calculated up to 3 hectares

            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
