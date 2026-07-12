<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pest_outbreaks', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('date_spotted');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->text('notes')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('pest_outbreaks', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'notes']);
        });
    }
};
