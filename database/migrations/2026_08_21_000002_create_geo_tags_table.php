<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side ledger of every Mobile GIS geo-tag capture (farm boundary
 * polygons and incident pins) synced from the technician's offline queue.
 * Provides the digital proof-of-measurement trail required by the DA-RSBSA
 * Georeferencing Guidelines (RCM Protocol).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable()->unique();
            $table->foreignUuid('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->string('rsbsa_no')->nullable();
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();

            $table->enum('geometry_type', ['polygon', 'marker']);
            $table->json('coordinates');

            $table->string('crop_planted', 100)->nullable();
            $table->string('crop_variety', 128)->nullable();
            $table->enum('incident_type', ['none', 'pest', 'calamity'])->default('none');
            $table->text('observations')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();

            // RCM Protocol: non-productive area deduction (>200 sqm infrastructure)
            $table->decimal('gross_area_sqm', 14, 2)->nullable();
            $table->decimal('non_productive_area_sqm', 12, 2)->default(0);
            $table->decimal('final_area_sqm', 14, 2)->nullable();
            $table->decimal('final_area_ha', 10, 4)->nullable();
            $table->boolean('has_discrepancy')->default(false);

            $table->foreignUuid('farm_plot_id')->nullable()->constrained('farm_plots')->nullOnDelete();
            $table->timestamp('sms_sent_at')->nullable();

            $table->timestamps();

            $table->index(['farmer_id', 'created_at']);
            $table->index('geometry_type');
            $table->index('has_discrepancy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_tags');
    }
};
