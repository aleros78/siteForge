<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Server;

class AuditService
{
    public function start(
        string $action,
        ?Project $project = null,
        ?Server $server = null,
        array $context = []
    ): AuditLog {
        return AuditLog::create([
            'project_id'   => $project?->id,
            'server_id'    => $server?->id,
            'action'       => $action,
            'status'       => 'running',
            'triggered_by' => auth()->user()?->email ?? 'system',
            'context'      => $context,
            'started_at'   => now(),
        ]);
    }

    public function success(AuditLog $log, string $output = '', int $exitCode = 0): AuditLog
    {
        $log->markAsSuccess($output, $exitCode);
        return $log->fresh();
    }

    public function fail(AuditLog $log, string $errorOutput = '', int $exitCode = 1): AuditLog
    {
        $log->markAsFailed($errorOutput, $exitCode);
        return $log->fresh();
    }

    public function log(
        string $action,
        ?Project $project = null,
        string $status = 'success',
        string $output = '',
        array $context = []
    ): AuditLog {
        return AuditLog::create([
            'project_id'   => $project?->id,
            'action'       => $action,
            'status'       => $status,
            'output'       => $output,
            'triggered_by' => auth()->user()?->email ?? 'system',
            'context'      => $context,
            'started_at'   => now(),
            'completed_at' => now(),
        ]);
    }
}
