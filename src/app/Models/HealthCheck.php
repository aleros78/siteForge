<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthCheck extends Model
{
    protected $fillable = [
        'project_id',
        'status',
        'containers_up',
        'port_responding',
        'http_ok',
        'http_status_code',
        'response_time_ms',
        'container_statuses',
        'error_message',
    ];

    protected $casts = [
        'containers_up'      => 'boolean',
        'port_responding'    => 'boolean',
        'http_ok'            => 'boolean',
        'http_status_code'   => 'integer',
        'response_time_ms'   => 'integer',
        'container_statuses' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'ok'      => 'success',
            'warning' => 'warning',
            'error'   => 'danger',
            default   => 'gray',
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'ok'      => 'heroicon-o-check-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            'error'   => 'heroicon-o-x-circle',
            default   => 'heroicon-o-question-mark-circle',
        };
    }
}
