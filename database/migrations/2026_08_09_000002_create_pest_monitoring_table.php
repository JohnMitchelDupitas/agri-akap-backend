<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pest_monitoring', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable()->unique();
            $table->foreignUuid('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('crop', 64)->nullable();
            $table->string('pest_name', 128)->nullable();
            $table->unsignedTinyInteger('incidence')->default(0);
            $table->string('severity', 32)->nullable();
            $table->text('advisory')->nullable();
            $table->boolean('is_outbreak')->default(false);
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('report_ref')->nullable();
            $table->string('item_distributed')->nullable();
            $table->string('quantity')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();

            $table->index(['farmer_id', 'created_at']);
            $table->index('is_outbreak');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pest_monitoring');
    }
};
