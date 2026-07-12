<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pre-calamity PCIC crop insurance enrollment records.
     */
    public function up(): void
    {
        Schema::create('pcic_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('farmer_id')->constrained('farmers')->cascadeOnDelete();
            $table->foreignUuid('farm_plot_id')->nullable()->constrained('farm_plots')->nullOnDelete();
            $table->string('crop_season');
            $table->unsignedSmallInteger('coverage_year');
            $table->string('commodity');
            $table->decimal('insured_area_ha', 8, 4);
            $table->string('policy_reference')->nullable();
            $table->foreignUuid('enrolled_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('enrolled_at');
            $table->enum('status', ['Active', 'Submitted', 'Withdrawn'])->default('Active');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcic_enrollments');
    }
};
