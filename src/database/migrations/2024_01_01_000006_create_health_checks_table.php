<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['ok', 'warning', 'error', 'unknown'])->default('unknown');
            $table->boolean('containers_up')->default(false);
            $table->boolean('port_responding')->default(false);
            $table->boolean('http_ok')->default(false);
            $table->integer('http_status_code')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->json('container_statuses')->nullable(); // stato singoli container
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_checks');
    }
};
