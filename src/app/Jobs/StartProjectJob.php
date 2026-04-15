<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\AuditService;
use App\Services\DockerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(public readonly Project $project) {}

    public function handle(DockerService $docker, AuditService $audit): void
    {
        $log = $audit->start('start', $this->project);

        $result = $docker->up($this->project);

        if ($result['success']) {
            $this->project->update(['status' => 'running']);
            $audit->success($log, $result['output']);
        } else {
            $this->project->update(['status' => 'error']);
            $audit->fail($log, $result['error']);
            throw new \RuntimeException("docker compose up fallito: " . $result['error']);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => 'error']);
    }
}
