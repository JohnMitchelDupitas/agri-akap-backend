<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_barangays', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->change();
            $table->decimal('longitude', 11, 8)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_barangays', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->change();
            $table->decimal('longitude', 10, 7)->change();
        });
    }
};
