<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_subsidy_beneficiaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')
                ->constrained('tbl_subsidy_programs')
                ->cascadeOnDelete();
            $table->string('farmer_rsbsa_no');
            $table->unsignedInteger('calculated_allocation');
            $table->enum('status', ['Pending', 'Claimed'])->default('Pending');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->foreign('farmer_rsbsa_no')
                ->references('rsbsa_no')
                ->on('farmers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unique(['program_id', 'farmer_rsbsa_no'], 'subsidy_program_farmer_unique');
            $table->index(['program_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_subsidy_beneficiaries');
    }
};
