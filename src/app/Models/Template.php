<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'type',
        'is_active',
        'is_builtin',
        'required_env_vars',
        'default_ports',
        'services',
        'icon',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'is_builtin'         => 'boolean',
        'required_env_vars'  => 'array',
        'default_ports'      => 'array',
        'services'           => 'array',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function files(): HasMany
    {
        return $this->hasMany(TemplateFile::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getDockerComposeStub(): ?TemplateFile
    {
        return $this->files()->where('type', 'docker-compose')->first();
    }

    public function getNginxStub(): ?TemplateFile
    {
        return $this->files()->where('type', 'nginx')->first();
    }

    public function getEnvStub(): ?TemplateFile
    {
        return $this->files()->where('type', 'env')->first();
    }
}
