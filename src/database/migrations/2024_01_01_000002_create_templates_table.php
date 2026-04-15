<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->enum('type', ['laravel', 'php', 'nodejs', 'static', 'custom'])->default('custom');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_builtin')->default(false);
            $table->json('required_env_vars')->nullable();
            $table->json('default_ports')->nullable();
            $table->json('services')->nullable(); // lista servizi Docker inclusi
            $table->string('icon')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('template_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('filename'); // es: docker-compose.stub
            $table->string('target_path'); // es: docker-compose.yml (relativo alla dir progetto)
            $table->longText('content');
            $table->enum('type', ['docker-compose', 'nginx', 'env', 'custom'])->default('custom');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_files');
        Schema::dropIfExists('templates');
    }
};
