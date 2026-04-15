<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Project;
use RuntimeException;

class BackupService
{
    public function __construct(
        private readonly DockerService $docker,
        private readonly AuditService $audit,
    ) {}

    /**
     * Esegue il backup del database di un progetto.
     * Presuppone che ci sia un container 'db' con MariaDB/MySQL nel compose.
     */
    public function backupDatabase(Project $project): Backup
    {
        $backup = Backup::create([
            'project_id' => $project->id,
            'type'       => 'database',
            'status'     => 'running',
            'disk'       => 'local',
        ]);

        $auditLog = $this->audit->start('backup', $project);

        try {
            $backupDir = storage_path("app/backups/{$project->slug}");
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = "db_{$project->slug}_" . now()->format('Ymd_His') . ".sql.gz";
            $filePath = "{$backupDir}/{$filename}";

            // Legge le credenziali DB dall'env del progetto
            $envVars = $project->env_vars ?? [];
            $dbDatabase = $envVars['db_database'] ?? ('db_' . str_replace('-', '_', $project->slug));
            $dbUsername = $envVars['db_username'] ?? 'root';
            $dbPassword = $envVars['db_password'] ?? $envVars['db_root_password'] ?? '';

            // Dump via docker exec
            $dumpCmd = [
                'mysqldump',
                '--single-transaction',
                '--routines',
                '--triggers',
                "-u{$dbUsername}",
                "-p{$dbPassword}",
                $dbDatabase,
            ];

            $result = $this->docker->execInContainer($project, 'db', $dumpCmd);

            if (!$result['success']) {
                throw new RuntimeException("mysqldump fallito: " . $result['error']);
            }

            // Comprimi e salva
            $compressed = gzencode($result['output'], 6);
            if (file_put_contents($filePath, $compressed) === false) {
                throw new RuntimeException("Impossibile salvare il backup: {$filePath}");
            }

            $fileSize = filesize($filePath);

            $backup->update([
                'status'    => 'completed',
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'expires_at'=> now()->addDays(30),
            ]);

            $project->update(['last_backed_up_at' => now()]);

            $this->audit->success($auditLog, "Backup completato: {$filename} ({$fileSize} bytes)");

        } catch (RuntimeException $e) {
            $backup->update([
                'status'       => 'failed',
                'error_output' => $e->getMessage(),
            ]);
            $this->audit->fail($auditLog, $e->getMessage());
            throw $e;
        }

        return $backup->fresh();
    }

    /**
     * Elimina backup scaduti o oltre la soglia.
     */
    public function pruneOldBackups(Project $project, int $keepLast = 10): int
    {
        $toDelete = Backup::where('project_id', $project->id)
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->skip($keepLast)
            ->get();

        $deleted = 0;
        foreach ($toDelete as $backup) {
            if ($backup->file_path && file_exists($backup->file_path)) {
                unlink($backup->file_path);
            }
            $backup->delete();
            $deleted++;
        }

        return $deleted;
    }
}
