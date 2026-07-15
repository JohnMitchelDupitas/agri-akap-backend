<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statutory report approval workflow (Pending → Verified → Finalized).
     */
    public function up(): void
    {
        Schema::create('report_workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_type');
            $table->foreignUuid('raw_data_collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('consolidator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('provincial_status', ['Pending', 'Verified', 'Finalized'])->default('Pending');
            $table->string('file_url')->nullable();
            $table->date('submission_date')->nullable();
            $table->json('report_parameters');
            $table->json('payload_snapshot')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_workflows');
    }
};
