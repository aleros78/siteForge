<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'host',
        'base_path',
        'ssh_user',
        'ssh_port',
        'ssh_private_key',
        'status',
        'docker_version',
        'is_local',
        'meta',
        'last_checked_at',
    ];

    protected $casts = [
        'is_local'       => 'boolean',
        'meta'           => 'array',
        'last_checked_at'=> 'datetime',
        'ssh_port'       => 'integer',
    ];

    protected $hidden = ['ssh_private_key'];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function getProjectPath(string $projectSlug): string
    {
        return rtrim($this->base_path, '/') . '/' . $projectSlug;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'online'  => 'success',
            'offline' => 'danger',
            default   => 'warning',
        };
    }
}
