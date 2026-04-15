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

class StopProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(public readonly Project $project) {}

    public function handle(DockerService $docker, AuditService $audit): void
    {
        $log = $audit->start('stop', $this->project);

        $result = $docker->stop($this->project);

        if ($result['success']) {
            $this->project->update(['status' => 'stopped']);
            $audit->success($log, $result['output']);
        } else {
            $audit->fail($log, $result['error']);
            throw new \RuntimeException("docker compose stop fallito: " . $result['error']);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => 'error']);
    }
}
