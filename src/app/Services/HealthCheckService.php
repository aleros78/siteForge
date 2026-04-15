<?php

namespace App\Services;

use App\Models\HealthCheck;
use App\Models\Project;
use Illuminate\Support\Facades\Http;

class HealthCheckService
{
    public function __construct(
        private readonly DockerService $docker,
    ) {}

    public function check(Project $project): HealthCheck
    {
        $data = [
            'project_id'         => $project->id,
            'containers_up'      => false,
            'port_responding'    => false,
            'http_ok'            => false,
            'http_status_code'   => null,
            'response_time_ms'   => null,
            'container_statuses' => [],
            'error_message'      => null,
        ];

        // 1. Check container status
        $containerStatuses = $this->docker->getContainerStatuses($project);
        $data['container_statuses'] = $containerStatuses;

        $runningCount = count(array_filter($containerStatuses, fn($c) => $c['status'] === 'running'));
        $totalCount = count($containerStatuses);
        $data['containers_up'] = $totalCount > 0 && $runningCount === $totalCount;

        if (!$data['containers_up'] && $totalCount > 0) {
            $data['error_message'] = "{$runningCount}/{$totalCount} container attivi";
        }

        // 2. HTTP health check
        if ($data['containers_up']) {
            $httpResult = $this->checkHttp($project);
            $data['port_responding']  = $httpResult['responding'];
            $data['http_ok']          = $httpResult['ok'];
            $data['http_status_code'] = $httpResult['status_code'];
            $data['response_time_ms'] = $httpResult['response_time_ms'];

            if (!$httpResult['ok'] && $httpResult['error']) {
                $data['error_message'] = $httpResult['error'];
            }
        }

        // 3. Calcola stato complessivo
        $data['status'] = $this->determineStatus($data);

        return HealthCheck::create($data);
    }

    private function checkHttp(Project $project): array
    {
        $url = "http://{$project->primary_domain}";
        $start = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withOptions(['verify' => false])
                ->get($url);

            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'responding'      => true,
                'ok'              => $response->status() < 500,
                'status_code'     => $response->status(),
                'response_time_ms'=> $ms,
                'error'           => null,
            ];
        } catch (\Exception $e) {
            return [
                'responding'      => false,
                'ok'              => false,
                'status_code'     => null,
                'response_time_ms'=> null,
                'error'           => $e->getMessage(),
            ];
        }
    }

    private function determineStatus(array $data): string
    {
        if (!$data['containers_up']) {
            return 'error';
        }

        if (!$data['http_ok']) {
            return 'warning';
        }

        return 'ok';
    }

    public function checkAll(): void
    {
        $projects = \App\Models\Project::where('status', 'running')->get();
        foreach ($projects as $project) {
            $this->check($project);
        }
    }
}
