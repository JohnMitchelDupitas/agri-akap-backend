<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_distribution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable()->unique();
            $table->foreignUuid('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rsbsa_id', 64);
            $table->string('item_dispensed');
            $table->string('quantity')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->uuid('program_id')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_distribution_logs');
    }
};
