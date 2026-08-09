<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pest_monitoring', function (Blueprint $table) {
            if (! Schema::hasColumn('pest_monitoring', 'crop_stage')) {
                $table->string('crop_stage', 64)->nullable()->after('crop');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pest_monitoring', function (Blueprint $table) {
            if (Schema::hasColumn('pest_monitoring', 'crop_stage')) {
                $table->dropColumn('crop_stage');
            }
        });
    }
};
