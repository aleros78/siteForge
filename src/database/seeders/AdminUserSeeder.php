<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'      => 'Admin',
            'email'     => env('ADMIN_EMAIL', 'admin@siteforge.local'),
            'password'  => Hash::make(env('ADMIN_PASSWORD', 'SiteForge2024!')),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->command->info('Admin user created: ' . env('ADMIN_EMAIL', 'admin@siteforge.local'));
    }
}
