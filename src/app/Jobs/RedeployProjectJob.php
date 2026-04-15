<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ProvisionProjectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RedeployProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public readonly Project $project) {}

    public function handle(ProvisionProjectService $service): void
    {
        $service->redeploy($this->project);
    }

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => 'error']);
    }
}
