<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign Keys
            $table->foreignUuid('farmer_id')->constrained('farmers')->cascadeOnDelete();
            $table->foreignUuid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUuid('distributed_by')->constrained('users')->comment('Technician who scanned the QR');

            // Sync & Audit details
            $table->enum('status', ['claimed', 'pending_sync'])->default('claimed');
            $table->string('device_id')->nullable()->comment('Tracks which mobile device logged this offline');
            $table->timestamp('claimed_at'); // Exact time of offline scan

            $table->timestamps();

            // SECURITY: Database-level lock against duplicate claims
            // A farmer can only exist ONCE per specific program ID.
            $table->unique(['farmer_id', 'program_id'], 'unique_farmer_program_claim');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributions');
    }
};
