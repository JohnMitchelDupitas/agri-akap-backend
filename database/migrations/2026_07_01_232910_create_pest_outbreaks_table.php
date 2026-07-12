<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pest_outbreaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('farm_plot_id')->constrained('farm_plots');
            $table->foreignUuid('technician_id')->constrained('users');
            $table->string('pest_name'); // e.g., Fall Armyworm, Brown Planthopper
            $table->enum('severity', ['Low', 'Medium', 'High', 'Critical']);
            $table->date('date_spotted');
            $table->string('status')->default('Active'); // Active, Contained, Resolved
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pest_outbreaks');
    }
};
