<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DA MAO Operational UI/UX: Add verification_status and rts_reason to support
     * the Return-for-Correction (RTS) document feedback loop.
     */
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->enum('verification_status', ['pending', 'approved', 'rts'])
                ->default('pending')
                ->after('qr_code_hash');
            $table->text('rts_reason')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('rts_reason');
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'rts_reason', 'verified_at']);
        });
    }
};
