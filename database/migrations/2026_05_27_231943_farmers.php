<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rsbsa_no')->unique()->nullable();
            $table->string('transaction_code')->unique();
            $table->string('photo_path')->nullable();

            // ── Part 1: Personal Information ──────────────────────────────
            $table->string('surname');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('ext_name')->nullable();
            $table->boolean('no_middle_name')->default(false);
            $table->boolean('no_ext_name')->default(false);
            $table->enum('sex', ['Male', 'Female']);

            // ── Permanent Address ─────────────────────────────────────────
            $table->string('permanent_house_no');
            $table->string('permanent_street');
            $table->string('permanent_brgy');
            $table->string('permanent_city');
            $table->string('permanent_province');
            $table->string('permanent_region');

            // ── Provincial / Mailing Address (optional) ───────────────────
            $table->string('provincial_house_no')->nullable();
            $table->string('provincial_street')->nullable();
            $table->string('provincial_brgy')->nullable();
            $table->string('provincial_city')->nullable();
            $table->string('provincial_province')->nullable();
            $table->string('provincial_region')->nullable();

            // ── Birth Details ─────────────────────────────────────────────
            $table->date('birthdate');
            $table->string('place_of_birth_city')->nullable();
            $table->string('place_of_birth_province')->nullable();

            // ── Contact ───────────────────────────────────────────────────
            $table->string('mobile_number');
            $table->boolean('is_mobile_owner')->default(true);
            $table->string('mobile_owner_first_name')->nullable();
            $table->string('mobile_owner_middle_name')->nullable();
            $table->string('mobile_owner_surname')->nullable();
            $table->string('mobile_owner_ext_name')->nullable();

            // ── Mother's Maiden Name ──────────────────────────────────────
            $table->string('mothers_maiden_first_name');
            $table->string('mothers_maiden_middle_name')->nullable();
            $table->string('mothers_maiden_surname');
            $table->string('mothers_maiden_ext_name')->nullable();

            // ── Part 2: Civil Status & Spouse ─────────────────────────────
            $table->enum('civil_status', [
                'Single',
                'Married',
                'Widow/er',
                'Legally Separated',
            ]);
            $table->string('spouse_first_name')->nullable();
            $table->string('spouse_middle_name')->nullable();
            $table->string('spouse_surname')->nullable();
            $table->string('spouse_ext_name')->nullable();

            // ── Education ─────────────────────────────────────────────────
            $table->enum('highest_education', [
                'Pre-school',
                'Elementary',
                'High School non K-12',
                'Junior High School K-12',
                'Senior High School K-12',
                'College',
                'Vocational',
                'Post-graduate',
                'None',
            ]);

            // ── Religion ──────────────────────────────────────────────────
            $table->string('religion')->nullable();

            // ── Government ID ─────────────────────────────────────────────
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();

            // ── Vulnerability & Membership ────────────────────────────────
            $table->boolean('is_icc_ip')->default(false);
            $table->string('icc_ip_name')->nullable();
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_4ps_beneficiary')->default(false);

            // ── Associations / Cooperatives ───────────────────────────────
            $table->string('association_1')->nullable();
            $table->string('association_2')->nullable();
            $table->string('association_3')->nullable();

            // ── Livelihood ────────────────────────────────────────────────
            $table->enum('livelihood_type', [
                'Farmer',
                'Farm Worker',
                'Fisher',
                'Agri-Youth',
            ]);

            $table->string('qr_code_hash')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
