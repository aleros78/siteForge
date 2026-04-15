<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['database', 'files', 'full'])->default('database');
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->string('disk')->default('local');
            $table->text('notes')->nullable();
            $table->longText('error_output')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
