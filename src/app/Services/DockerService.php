<?php

namespace App\Services;

use App\Models\Project;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DockerService
{
    private const TIMEOUT_DEFAULT = 60;
    private const TIMEOUT_DEPLOY  = 300;
    private const TIMEOUT_LOGS    = 30;

    // ── Core command runner ───────────────────────────────────────────────────

    /**
     * Esegue un comando Docker Compose nel contesto di un progetto.
     * Usa sempre un allowlist di comandi per sicurezza.
     */
    public function runCompose(Project $project, array $args, int $timeout = self::TIMEOUT_DEFAULT): array
    {
        $command = array_merge(
            ['docker', 'compose', '-f', $project->getDockerComposePath(), '-p', $project->getComposeProjectName()],
            $args
        );

        return $this->run($command, $project->base_path, $timeout);
    }

    public function run(array $command, string $cwd = '/', int $timeout = self::TIMEOUT_DEFAULT): array
    {
        $process = new Process($command, $cwd);
        $process->setTimeout($timeout);
        $process->run();

        return [
            'success'    => $process->isSuccessful(),
            'exit_code'  => $process->getExitCode(),
            'output'     => $process->getOutput(),
            'error'      => $process->getErrorOutput(),
        ];
    }

    // ── Project lifecycle ─────────────────────────────────────────────────────

    public function up(Project $project, bool $build = false): array
    {
        $args = ['up', '-d', '--remove-orphans'];
        if ($build) {
            $args[] = '--build';
        }
        return $this->runCompose($project, $args, self::TIMEOUT_DEPLOY);
    }

    public function down(Project $project, bool $removeVolumes = false): array
    {
        $args = ['down'];
        if ($removeVolumes) {
            $args[] = '-v';
        }
        return $this->runCompose($project, $args);
    }

    public function start(Project $project): array
    {
        return $this->runCompose($project, ['start']);
    }

    public function stop(Project $project): array
    {
        return $this->runCompose($project, ['stop']);
    }

    public function restart(Project $project): array
    {
        return $this->runCompose($project, ['restart']);
    }

    public function pull(Project $project): array
    {
        return $this->runCompose($project, ['pull'], self::TIMEOUT_DEPLOY);
    }

    public function build(Project $project): array
    {
        return $this->runCompose($project, ['build', '--no-cache'], self::TIMEOUT_DEPLOY);
    }

    // ── Logs ──────────────────────────────────────────────────────────────────

    public function logs(Project $project, int $lines = 100, ?string $service = null): array
    {
        $args = ['logs', '--no-color', "--tail={$lines}"];
        if ($service) {
            $args[] = $service;
        }
        return $this->runCompose($project, $args, self::TIMEOUT_LOGS);
    }

    // ── Status ────────────────────────────────────────────────────────────────

    public function ps(Project $project): array
    {
        return $this->runCompose($project, ['ps', '--format', 'json']);
    }

    public function isRunning(Project $project): bool
    {
        $result = $this->runCompose($project, ['ps', '--services', '--filter', 'status=running']);
        return $result['success'] && !empty(trim($result['output']));
    }

    public function getContainerStatuses(Project $project): array
    {
        $result = $this->runCompose($project, ['ps', '--format', 'json']);

        if (!$result['success'] || empty(trim($result['output']))) {
            return [];
        }

        $statuses = [];
        foreach (explode("\n", trim($result['output'])) as $line) {
            if (empty($line)) continue;
            $data = json_decode($line, true);
            if ($data) {
                $statuses[] = [
                    'name'   => $data['Name'] ?? $data['Service'] ?? 'unknown',
                    'status' => $data['State'] ?? $data['Status'] ?? 'unknown',
                    'health' => $data['Health'] ?? null,
                ];
            }
        }

        return $statuses;
    }

    // ── Docker system ─────────────────────────────────────────────────────────

    public function version(): array
    {
        return $this->run(['docker', 'version', '--format', '{{.Server.Version}}']);
    }

    public function isDockerAvailable(): bool
    {
        $result = $this->run(['docker', 'info', '--format', 'json']);
        return $result['success'];
    }

    // ── Exec in container ─────────────────────────────────────────────────────

    /**
     * Esegue un comando in un container (solo comandi predefiniti sicuri).
     */
    public function execInContainer(Project $project, string $service, array $allowedCommand): array
    {
        $args = array_merge(['exec', '-T', $service], $allowedCommand);
        return $this->runCompose($project, $args);
    }
}
