<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the RSBSA livelihood sub-classification (e.g. Rice, Corn, Aquaculture)
     * that further specifies the broad livelihood_type.
     */
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('livelihood_detail')->nullable()->after('livelihood_type');
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn('livelihood_detail');
        });
    }
};
