<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->uuid('id')->primary(); // 1. Fixed: UUID for offline sync safety

            // 2. Fixed: Matches the string UUID format of the farmers table
            $table->foreignUuid('farmer_id')
                  ->constrained('farmers')
                  ->cascadeOnDelete();

            // ── Part 3: Farm Parcel Information ───────────────────────────
            $table->string('location_brgy');
            $table->string('location_city');
            $table->string('location_province');

            // 3. Strategic Addition: GPS mapping belongs to the specific plot
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->decimal('total_parcel_area_ha', 10, 4);
            $table->boolean('is_ancestral_domain')->default(false);
            $table->boolean('is_agrarian_reform_beneficiary')->default(false);

            $table->enum('ownership_type', [
                'Registered Owner',
                'Tenant',
                'Lessee',
                'Others',
            ]);

            $table->string('land_owner_first_name')->nullable();
            $table->string('land_owner_surname')->nullable();
            $table->string('land_owner_ext_name')->nullable();
            $table->string('proof_of_ownership_document');

            // ── Commodity Details ─────────────────────────────────────────
            $table->string('commodity');
            $table->decimal('size_ha', 10, 4);
            $table->integer('no_of_heads_or_trees')->nullable();

            $table->enum('farm_type', [
                'Irrigated',
                'Rainfed Upland',
                'Rainfed Lowland',
                'Urban/Peri-Urban',
            ]);

            $table->boolean('is_organic')->default(false);
            $table->string('cropping_schedule');

            $table->string('rotational_tiller_full_name')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Added for auditability
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_plots');
    }
};
