<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectDomain;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProvisionProjectService
{
    public function __construct(
        private readonly DockerService $docker,
        private readonly TemplateEngineService $templateEngine,
        private readonly AuditService $audit,
        private readonly HealthCheckService $healthCheck,
    ) {}

    /**
     * Provisioning completo di un progetto:
     * 1. Crea struttura directory
     * 2. Genera file da template
     * 3. Esegue docker compose up
     * 4. Verifica salute
     * 5. Aggiorna stato progetto
     */
    public function provision(Project $project): void
    {
        $auditLog = $this->audit->start('provision', $project);
        $outputLines = [];

        try {
            $project->update(['status' => 'provisioning']);

            // 1. Crea directory progetto
            $this->createProjectDirectory($project, $outputLines);

            // 2. Genera file da template
            $this->generateProjectFiles($project, $outputLines);

            // 3. Registra dominio primario
            $this->ensurePrimaryDomain($project);

            // 4. Avvia Docker Compose
            $this->startDockerStack($project, $outputLines);

            // 5. Verifica salute
            $this->verifyProjectHealth($project, $outputLines);

            // 6. Aggiorna stato
            $project->update([
                'status'           => 'running',
                'last_deployed_at' => now(),
            ]);

            $this->audit->success($auditLog, implode("\n", $outputLines));

        } catch (RuntimeException $e) {
            $project->update(['status' => 'error']);
            $this->audit->fail($auditLog, $e->getMessage());
            Log::error("Provisioning failed for project {$project->slug}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Re-deploy completo (rebuild + restart).
     */
    public function redeploy(Project $project): void
    {
        $auditLog = $this->audit->start('redeploy', $project);
        $outputLines = [];

        try {
            $project->update(['status' => 'rebuilding']);

            // Rigenera i file di configurazione
            $this->generateProjectFiles($project, $outputLines);

            // Build + up
            $result = $this->docker->up($project, build: true);
            $this->assertDockerSuccess($result, 'docker compose up --build');
            $outputLines[] = $result['output'];

            $project->update([
                'status'           => 'running',
                'last_deployed_at' => now(),
            ]);

            $this->audit->success($auditLog, implode("\n", $outputLines));

        } catch (RuntimeException $e) {
            $project->update(['status' => 'error']);
            $this->audit->fail($auditLog, $e->getMessage());
            throw $e;
        }
    }

    // ── Private steps ─────────────────────────────────────────────────────────

    private function createProjectDirectory(Project $project, array &$output): void
    {
        $dirs = [
            $project->base_path,
            $project->base_path . '/docker/nginx',
            $project->base_path . '/docker/php',
            $project->base_path . '/backups',
            $project->base_path . '/logs',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new RuntimeException("Impossibile creare la directory: {$dir}");
            }
        }

        $output[] = "Directory progetto creata: {$project->base_path}";
    }

    private function generateProjectFiles(Project $project, array &$output): void
    {
        $files = $this->templateEngine->generateFiles($project);

        foreach ($files as $file) {
            $dir = dirname($file['path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (file_put_contents($file['path'], $file['content']) === false) {
                throw new RuntimeException("Impossibile scrivere il file: {$file['path']}");
            }

            $output[] = "File generato: {$file['path']}";
        }
    }

    private function ensurePrimaryDomain(Project $project): void
    {
        ProjectDomain::firstOrCreate(
            ['project_id' => $project->id, 'domain' => $project->primary_domain],
            ['is_primary' => true, 'status' => 'active']
        );
    }

    private function startDockerStack(Project $project, array &$output): void
    {
        $result = $this->docker->up($project);
        $this->assertDockerSuccess($result, 'docker compose up');
        $output[] = $result['output'];

        // Breve attesa per stabilizzazione container
        sleep(3);
    }

    private function verifyProjectHealth(Project $project, array &$output): void
    {
        $check = $this->healthCheck->check($project);
        $output[] = "Health check: {$check->status}";

        if ($check->status === 'error') {
            throw new RuntimeException("Il progetto non risponde dopo il deploy: {$check->error_message}");
        }
    }

    private function assertDockerSuccess(array $result, string $command): void
    {
        if (!$result['success']) {
            throw new RuntimeException(
                "Comando '{$command}' fallito (exit code: {$result['exit_code']})\n" .
                $result['error']
            );
        }
    }
}
