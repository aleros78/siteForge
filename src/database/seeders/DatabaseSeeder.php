<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Solo se non ci sono già dati (idempotente)
        if (\App\Models\User::count() === 0) {
            $this->call(AdminUserSeeder::class);
        }

        if (\App\Models\Server::count() === 0) {
            $this->call(LocalServerSeeder::class);
        }

        if (\App\Models\Template::count() === 0) {
            $this->call(BuiltinTemplatesSeeder::class);
        }
    }
}
