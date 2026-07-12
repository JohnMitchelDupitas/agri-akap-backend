<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('farm_plot_id')->constrained('farm_plots');
            $table->foreignUuid('technician_id')->constrained('users');
            $table->string('calamity_name'); // e.g., Typhoon Ompong, El Nino
            $table->date('date_of_calamity');
            $table->decimal('damage_percentage', 5, 2); // e.g. 85.50%
            $table->decimal('estimated_value_lost', 10, 2)->nullable();
            $table->string('photo_evidence_path')->nullable();

            // Geotagging Fields
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->enum('status', ['Pending', 'Verified', 'Claimed'])->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_assessments');
    }
};
