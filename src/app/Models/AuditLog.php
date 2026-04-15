<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'project_id',
        'server_id',
        'action',
        'status',
        'output',
        'error_output',
        'exit_code',
        'triggered_by',
        'context',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'context'      => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'exit_code'    => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function markAsRunning(): void
    {
        $this->update([
            'status'     => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsSuccess(string $output = '', int $exitCode = 0): void
    {
        $this->update([
            'status'       => 'success',
            'output'       => $output,
            'exit_code'    => $exitCode,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorOutput = '', int $exitCode = 1): void
    {
        $this->update([
            'status'       => 'failed',
            'error_output' => $errorOutput,
            'exit_code'    => $exitCode,
            'completed_at' => now(),
        ]);
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }
        return null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'success',
            'failed'  => 'danger',
            'running' => 'info',
            default   => 'gray',
        };
    }
}
