<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand the users.role enum to include Barangay Officials who
     * pre-assess disaster damage before final MAO approval.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'technician', 'barangay_official') NOT NULL DEFAULT 'technician'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'technician') NOT NULL DEFAULT 'technician'");
    }
};
