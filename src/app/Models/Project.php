<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'server_id',
        'template_id',
        'base_path',
        'primary_domain',
        'environment',
        'status',
        'env_vars',
        'meta',
        'cloned_from_id',
        'last_deployed_at',
        'last_backed_up_at',
    ];

    protected $casts = [
        'env_vars'         => 'array',
        'meta'             => 'array',
        'last_deployed_at' => 'datetime',
        'last_backed_up_at'=> 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(ProjectDomain::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest();
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class)->latest();
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(HealthCheck::class)->latest();
    }

    public function latestHealthCheck(): HasOne
    {
        return $this->hasOne(HealthCheck::class)->latestOfMany();
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'cloned_from_id');
    }

    public function clones(): HasMany
    {
        return $this->hasMany(Project::class, 'cloned_from_id');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    public function isProvisioning(): bool
    {
        return in_array($this->status, ['provisioning', 'rebuilding', 'cloning']);
    }

    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'running'     => 'success',
            'stopped'     => 'warning',
            'error'       => 'danger',
            'provisioning',
            'rebuilding',
            'cloning'     => 'info',
            default       => 'gray',
        };
    }

    // ── Path helpers ─────────────────────────────────────────────────────────

    public function getDockerComposePath(): string
    {
        return $this->base_path . '/docker-compose.yml';
    }

    public function getEnvPath(): string
    {
        return $this->base_path . '/.env';
    }

    public function getNginxConfigPath(): string
    {
        return $this->base_path . '/docker/nginx/default.conf';
    }

    public function getComposeProjectName(): string
    {
        return 'sf_' . $this->slug;
    }
}
