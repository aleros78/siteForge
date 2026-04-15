<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // provision, start, stop, restart, backup, clone, delete
            $table->enum('status', ['pending', 'running', 'success', 'failed'])->default('pending');
            $table->longText('output')->nullable();
            $table->longText('error_output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('triggered_by')->nullable(); // user email o "system"
            $table->json('context')->nullable(); // dati extra sull'operazione
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'action']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
