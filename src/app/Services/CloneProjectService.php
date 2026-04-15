<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Str;
use RuntimeException;

class CloneProjectService
{
    public function __construct(
        private readonly ProvisionProjectService $provision,
        private readonly AuditService $audit,
    ) {}

    /**
     * Clona un progetto esistente con un nuovo slug e dominio.
     */
    public function clone(Project $source, string $newName, string $newDomain, bool $cloneDatabase = false): Project
    {
        $auditLog = $this->audit->start('clone', $source, context: [
            'target_name'   => $newName,
            'target_domain' => $newDomain,
        ]);

        try {
            $newSlug = Str::slug($newName);

            if (Project::where('slug', $newSlug)->exists()) {
                throw new RuntimeException("Esiste già un progetto con slug: {$newSlug}");
            }

            // Crea nuovo record progetto copiando le impostazioni
            $clone = Project::create([
                'name'           => $newName,
                'slug'           => $newSlug,
                'description'    => "Clone di: {$source->name}",
                'server_id'      => $source->server_id,
                'template_id'    => $source->template_id,
                'base_path'      => $source->server->getProjectPath($newSlug),
                'primary_domain' => $newDomain,
                'environment'    => $source->environment,
                'status'         => 'cloning',
                'env_vars'       => $source->env_vars,
                'cloned_from_id' => $source->id,
            ]);

            // Provisioning completo del clone
            $this->provision->provision($clone);

            // TODO: copia database se richiesto
            // if ($cloneDatabase) { ... }

            $this->audit->success($auditLog, "Clone completato: {$clone->slug}");

            return $clone->fresh();

        } catch (RuntimeException $e) {
            $this->audit->fail($auditLog, $e->getMessage());
            throw $e;
        }
    }
}
