<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DA "3-Attempt Rule": logs every farmer refusal to consent to
 * georeferencing. A farmer with 3 logged attempts becomes eligible for the
 * RSBSA exclusion protocol, reviewed by MAO staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_tag_refusals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable()->unique();
            $table->foreignUuid('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->string('rsbsa_no')->nullable();
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();

            $table->unsignedTinyInteger('attempt_number');
            $table->text('reason');

            $table->timestamps();

            $table->index(['farmer_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_tag_refusals');
    }
};
