<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing sms_broadcasts ledger (tbl-equivalent) with
 * Manual vs Automated_Weather trigger tracking for climate advisories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_broadcasts', function (Blueprint $table) {
            $table->string('trigger_type', 32)
                ->default('Manual')
                ->after('message_body');
            $table->string('alert_type', 64)
                ->nullable()
                ->after('trigger_type');
        });
    }

    public function down(): void
    {
        Schema::table('sms_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['trigger_type', 'alert_type']);
        });
    }
};
