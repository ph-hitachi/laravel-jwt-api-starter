<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ─────────────────────────────────────────────────────────
        User::create([
            'name'      => 'Admin User',
            'email'     => 'admin@example.com',
            'password'  => Hash::make('Admin1234!'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // ── Regular User ──────────────────────────────────────────────────
        User::create([
            'name'      => 'Standard User',
            'email'     => 'user@example.com',
            'password'  => Hash::make('User1234!'),
            'role'      => 'user',
            'is_active' => true,
        ]);
    }
}
