<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable()->unique();
            $table->foreignUuid('farmer_id')->constrained('farmers');
            $table->foreignUuid('farm_plot_id')->nullable()->constrained('farm_plots')->nullOnDelete();
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('crop_type', 64);
            $table->string('variety', 128)->nullable();
            $table->decimal('area_harvested', 10, 4);
            $table->decimal('total_yield', 12, 2);
            $table->string('yield_unit', 64)->nullable();
            $table->date('date_harvested');
            $table->string('farm_location', 255)->nullable();
            $table->timestamps();

            $table->index(['farmer_id', 'date_harvested']);
            $table->index('crop_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_logs');
    }
};
