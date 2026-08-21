<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FFRS 2.0 Protocol — Automated Deduplication Engine
 *
 * Adds a soft-flag for probable duplicate farmer records. When a new enrollment
 * matches an existing farmer's surname + first_name + birthdate, the backend no
 * longer aborts — instead it saves the record and marks `is_probable_duplicate = true`.
 * Admin staff can then review and merge these via the web dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->boolean('is_probable_duplicate')->default(false)->after('qr_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn('is_probable_duplicate');
        });
    }
};
