<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planting_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable()->unique();
            $table->foreignUuid('farmer_id')->constrained('farmers');
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('crop_type', 64);
            $table->string('variety', 128);
            $table->decimal('area_planted', 10, 4);
            $table->date('date_planted');
            $table->string('status', 64)->default('Active');
            $table->string('water_source', 64)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();

            $table->index(['farmer_id', 'date_planted']);
            $table->index('crop_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planting_logs');
    }
};
