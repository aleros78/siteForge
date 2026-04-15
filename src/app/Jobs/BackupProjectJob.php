<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BackupProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(
        public readonly Project $project,
        public readonly string $type = 'database',
    ) {}

    public function handle(BackupService $backup): void
    {
        Log::info("BackupProjectJob started", ['project' => $this->project->slug, 'type' => $this->type]);

        match ($this->type) {
            'database' => $backup->backupDatabase($this->project),
            default    => throw new \InvalidArgumentException("Tipo backup non supportato: {$this->type}"),
        };

        // Pruning vecchi backup
        $backup->pruneOldBackups($this->project);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("BackupProjectJob failed", [
            'project' => $this->project->slug,
            'error'   => $exception->getMessage(),
        ]);
    }
}
