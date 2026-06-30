<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('target_barangay')->nullable(); // e.g., 'San Fabian' or 'All'
            $table->string('target_commodity')->nullable(); // e.g., 'Palay', 'Corn'
            $table->text('message_body');
            $table->integer('recipient_count');
            $table->string('status')->default('sent'); // sent, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_broadcasts');
    }
};