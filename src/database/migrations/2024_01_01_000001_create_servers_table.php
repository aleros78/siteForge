<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host')->default('localhost');
            $table->string('base_path')->default('/opt/siteforge/projects');
            $table->string('ssh_user')->nullable();
            $table->integer('ssh_port')->default(22);
            $table->text('ssh_private_key')->nullable();
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->string('docker_version')->nullable();
            $table->boolean('is_local')->default(true);
            $table->json('meta')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
