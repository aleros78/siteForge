<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('server_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            $table->string('base_path'); // path assoluto directory progetto
            $table->string('primary_domain');
            $table->enum('environment', ['production', 'staging', 'development'])->default('production');
            $table->enum('status', [
                'pending',
                'provisioning',
                'running',
                'stopped',
                'error',
                'rebuilding',
                'cloning',
            ])->default('pending');
            $table->json('env_vars')->nullable(); // variabili .env custom
            $table->json('meta')->nullable(); // dati extra (porte assegnate, etc.)
            $table->foreignId('cloned_from_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamp('last_backed_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->boolean('is_primary')->default(false);
            $table->boolean('https_enabled')->default(false);
            $table->enum('status', ['active', 'pending', 'error'])->default('pending');
            $table->timestamps();

            $table->unique(['project_id', 'domain']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_domains');
        Schema::dropIfExists('projects');
    }
};
