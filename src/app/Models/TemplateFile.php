<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateFile extends Model
{
    protected $fillable = [
        'template_id',
        'filename',
        'target_path',
        'content',
        'type',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function renderWith(array $vars): string
    {
        $content = $this->content;

        foreach ($vars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        return $content;
    }
}
