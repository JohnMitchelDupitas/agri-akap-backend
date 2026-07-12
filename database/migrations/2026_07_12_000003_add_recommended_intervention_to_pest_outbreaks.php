<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the prescriptive, municipality-approved countermeasure resolved
     * from the pest signature + severity at report time.
     */
    public function up(): void
    {
        Schema::table('pest_outbreaks', function (Blueprint $table) {
            $table->text('recommended_intervention')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pest_outbreaks', function (Blueprint $table) {
            $table->dropColumn('recommended_intervention');
        });
    }
};
