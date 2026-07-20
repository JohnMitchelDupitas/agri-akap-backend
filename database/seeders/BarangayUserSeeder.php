<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\EchagueBarangays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One barangay_official encoder account per Echague barangay (64).
 * Login: brgy_{slug}@echague.local / Echague2026!
 */
class BarangayUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EchagueBarangays::ALL as $barangay) {
            $slug = Str::slug($barangay, '_');
            $email = "brgy_{$slug}@echague.local";

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "Barangay Encoder — {$barangay}",
                    'password' => Hash::make('Echague2026!'),
                    'role' => 'barangay_official',
                    'assigned_barangay' => $barangay,
                    'is_active' => true,
                ]
            );
        }
    }
}
