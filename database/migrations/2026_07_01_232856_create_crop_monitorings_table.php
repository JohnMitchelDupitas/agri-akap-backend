<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_monitorings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('farm_plot_id')->constrained('farm_plots');
            $table->foreignUuid('technician_id')->constrained('users');
            $table->string('crop_planted'); // e.g., Rice, Corn, Tobacco
            $table->string('season'); // e.g., Wet, Dry
            $table->year('year');
            $table->decimal('soil_ph', 4, 2)->nullable(); // Tracks soil acidity
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_monitorings');
    }
};
