<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Template;
use App\Models\TemplateFile;

class TemplateEngineService
{
    /**
     * Genera i placeholder di sostituzione per un progetto.
     */
    public function getPlaceholders(Project $project): array
    {
        return [
            'project_slug'        => $project->slug,
            'project_name'        => $project->name,
            'project_domain'      => $project->primary_domain,
            'project_path'        => $project->base_path,
            'compose_project_name'=> $project->getComposeProjectName(),
            'environment'         => $project->environment,
            'app_key'             => $this->generateAppKey(),
            'db_database'         => 'db_' . str_replace('-', '_', $project->slug),
            'db_username'         => 'u_' . str_replace('-', '_', $project->slug),
            'db_password'         => $this->generateSecret(24),
            'db_root_password'    => $this->generateSecret(32),
            'app_env'             => $project->environment === 'production' ? 'production' : 'local',
            'app_debug'           => $project->environment === 'production' ? 'false' : 'true',
        ] + ($project->env_vars ?? []);
    }

    /**
     * Renderizza il contenuto di un template file sostituendo i placeholder.
     */
    public function render(TemplateFile $file, array $placeholders): string
    {
        return $file->renderWith($placeholders);
    }

    /**
     * Genera tutti i file di un template per un progetto.
     * Ritorna array di ['path' => '...', 'content' => '...']
     */
    public function generateFiles(Project $project): array
    {
        $template = $project->template()->with('files')->firstOrFail();
        $placeholders = $this->getPlaceholders($project);
        $generated = [];

        foreach ($template->files as $file) {
            $targetPath = $project->base_path . '/' . ltrim($file->target_path, '/');
            $content = $file->renderWith($placeholders);

            $generated[] = [
                'path'    => $targetPath,
                'content' => $content,
                'type'    => $file->type,
            ];
        }

        return $generated;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private function generateSecret(int $length = 24): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
}
