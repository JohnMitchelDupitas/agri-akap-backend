<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rural farmers often lack formal house numbers and street names.
     * Making these nullable prevents DB crashes from empty form fields.
     */
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('permanent_house_no')->nullable()->change();
            $table->string('permanent_street')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('permanent_house_no')->nullable(false)->change();
            $table->string('permanent_street')->nullable(false)->change();
        });
    }
};
