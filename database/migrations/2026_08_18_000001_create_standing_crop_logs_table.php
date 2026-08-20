<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_crop_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable()->unique();
            $table->foreignUuid('farmer_id')->constrained('farmers');
            $table->foreignUuid('farm_plot_id')->nullable()->constrained('farm_plots')->nullOnDelete();
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('crop_type', 64);
            $table->string('variety', 128);
            $table->decimal('area_ha', 10, 4);
            $table->string('growth_stage', 64)->nullable();
            $table->date('est_harvest_date');
            $table->string('farm_location', 255)->nullable();
            $table->timestamps();

            $table->index(['farmer_id', 'est_harvest_date']);
            $table->index('crop_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_crop_logs');
    }
};
