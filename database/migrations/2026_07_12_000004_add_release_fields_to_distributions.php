<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capture the released item snapshot, geo-tag, and photo voucher path at
     * the moment a technician authorizes a subsidy release.
     */
    public function up(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->string('item_released')->nullable()->after('quantity_claimed');
            $table->decimal('geo_tag_lat', 10, 8)->nullable()->after('item_released');
            $table->decimal('geo_tag_long', 11, 8)->nullable()->after('geo_tag_lat');
            $table->string('photo_proof_path')->nullable()->after('geo_tag_long');
        });
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropColumn(['item_released', 'geo_tag_lat', 'geo_tag_long', 'photo_proof_path']);
        });
    }
};
