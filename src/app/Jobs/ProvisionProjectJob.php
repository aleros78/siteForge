<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ProvisionProjectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1; // No retry automatici per il provisioning

    public function __construct(
        public readonly Project $project,
    ) {}

    public function handle(ProvisionProjectService $service): void
    {
        Log::info("ProvisionProjectJob started", ['project' => $this->project->slug]);

        try {
            $service->provision($this->project);
            Log::info("ProvisionProjectJob completed", ['project' => $this->project->slug]);
        } catch (\Throwable $e) {
            Log::error("ProvisionProjectJob failed", [
                'project' => $this->project->slug,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => 'error']);
        Log::error("ProvisionProjectJob permanently failed", [
            'project' => $this->project->slug,
            'error'   => $exception->getMessage(),
        ]);
    }
}
