<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executive stock-management config: minimum reorder threshold and the
     * barangays targeted by the program's active distribution cycle.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->integer('reorder_level')->nullable()->after('remaining_quantity');
            $table->json('target_barangays')->nullable()->after('reorder_level');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['reorder_level', 'target_barangays']);
        });
    }
};
