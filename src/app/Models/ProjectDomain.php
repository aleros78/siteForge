<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDomain extends Model
{
    protected $fillable = [
        'project_id',
        'domain',
        'is_primary',
        'https_enabled',
        'status',
    ];

    protected $casts = [
        'is_primary'    => 'boolean',
        'https_enabled' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getUrlAttribute(): string
    {
        $scheme = $this->https_enabled ? 'https' : 'http';
        return "{$scheme}://{$this->domain}";
    }
}
