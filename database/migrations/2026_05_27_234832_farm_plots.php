<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('farmer_id')->constrained('farmers')->cascadeOnDelete();

            // Location
            $table->string('location_brgy');
            $table->string('location_city');
            $table->string('location_province');
            $table->decimal('latitude', 10, 8)->nullable(); // 👈 Added nullable
            $table->decimal('longitude', 11, 8)->nullable(); // 👈 Added nullable

            // Ownership & Tenurial
            $table->decimal('total_parcel_area_ha', 10, 4);
            $table->boolean('is_ancestral_domain')->default(false);
            $table->boolean('is_agrarian_reform_beneficiary')->default(false);
            $table->string('ownership_type');
            
            // Land Owner details (Optional if the farmer is the registered owner)
            $table->string('land_owner_first_name')->nullable(); // 👈 Added nullable
            $table->string('land_owner_surname')->nullable();    // 👈 Added nullable
            $table->string('land_owner_ext_name')->nullable();   // 👈 Added nullable
            $table->string('proof_of_ownership_document');

            // Commodity
            $table->string('commodity');
            $table->decimal('size_ha', 10, 4);
            $table->integer('no_of_heads_or_trees')->nullable(); // 👈 Added nullable
            $table->string('farm_type');
            $table->boolean('is_organic')->default(false);
            
            // Scheduling & Remarks
            $table->string('cropping_schedule')->nullable(); // 👈 FIXED: Added nullable
            $table->string('rotational_tiller_full_name')->nullable(); // 👈 Added nullable
            $table->text('remarks')->nullable(); // Already nullable

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_plots');
    }
};
