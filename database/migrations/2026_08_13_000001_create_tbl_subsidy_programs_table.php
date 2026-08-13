<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_subsidy_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('program_name');
            $table->enum('target_crop', ['Rice', 'Corn']);
            $table->decimal('max_hectares_limit', 10, 4);
            $table->unsignedInteger('items_per_hectare');
            $table->enum('status', ['Draft', 'Active', 'Completed'])->default('Draft');
            $table->timestamps();

            $table->index(['status', 'target_crop']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_subsidy_programs');
    }
};
