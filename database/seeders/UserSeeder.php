<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create the Default System Administrator
        User::create([
            'name' => 'MAO Administrator',
            'email' => 'admin@mao.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // 2. Create a Default Field Technician for Mobile App testing
        User::create([
            'name' => 'John Field Technician',
            'email' => 'tech@mao.com',
            'password' => Hash::make('password123'),
            'role' => 'technician',
            'is_active' => true,
        ]);

        // 3. Create a Deactivated User to test UI login restrictions
        User::create([
            'name' => 'Suspended Tech',
            'email' => 'suspended@mao.com',
            'password' => Hash::make('password123'),
            'role' => 'technician',
            'is_active' => false,
        ]);

        // Note: Because we are using the User model, our HasUuid trait
        // is automatically generating the 36-character UUID primary keys behind the scenes!
    }
}
