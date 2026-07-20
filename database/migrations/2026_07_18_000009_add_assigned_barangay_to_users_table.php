<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope barangay officials to a single Echague barangay for data isolation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('assigned_barangay')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('assigned_barangay');
        });
    }
};
