<?php

namespace Database\Seeders;

use App\Models\Server;
use Illuminate\Database\Seeder;

class LocalServerSeeder extends Seeder
{
    public function run(): void
    {
        Server::create([
            'name'      => 'Local Server',
            'host'      => 'localhost',
            'base_path' => env('PROJECTS_BASE_PATH', '/opt/siteforge/projects'),
            'is_local'  => true,
            'status'    => 'unknown',
        ]);

        $this->command->info('Local server created.');
    }
}
