<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDomain;

class TraefikService
{
    /**
     * Genera le label Traefik per un progetto nel docker-compose.
     */
    public function generateLabels(Project $project): array
    {
        $slug = $project->getComposeProjectName();
        $domain = $project->primary_domain;
        $https = false; // sarà true quando Let's Encrypt è configurato

        $labels = [
            'traefik.enable=true',
            "traefik.docker.network=traefik_public",
            "traefik.http.routers.{$slug}.rule=Host(`{$domain}`)",
            "traefik.http.routers.{$slug}.entrypoints=web",
            "traefik.http.services.{$slug}.loadbalancer.server.port=80",
        ];

        // Aggiungi domini extra
        foreach ($project->domains as $dom) {
            if (!$dom->is_primary) {
                $labels[] = "traefik.http.routers.{$slug}-extra-{$dom->id}.rule=Host(`{$dom->domain}`)";
                $labels[] = "traefik.http.routers.{$slug}-extra-{$dom->id}.service={$slug}";
            }
        }

        if ($https) {
            $labels[] = "traefik.http.routers.{$slug}-secure.entrypoints=websecure";
            $labels[] = "traefik.http.routers.{$slug}-secure.rule=Host(`{$domain}`)";
            $labels[] = "traefik.http.routers.{$slug}-secure.tls=true";
            $labels[] = "traefik.http.routers.{$slug}-secure.tls.certresolver=letsencrypt";
        }

        return $labels;
    }

    /**
     * Genera il blocco labels nel formato YAML per docker-compose.
     */
    public function generateLabelsYaml(Project $project): string
    {
        $labels = $this->generateLabels($project);
        $yaml = "    labels:\n";
        foreach ($labels as $label) {
            $yaml .= "      - \"{$label}\"\n";
        }
        return $yaml;
    }

    /**
     * Genera un file di configurazione dinamica Traefik per un progetto.
     * Utile per router/middleware custom.
     */
    public function generateDynamicConfig(Project $project): string
    {
        $slug = $project->getComposeProjectName();
        $domain = $project->primary_domain;

        return <<<YAML
http:
  routers:
    {$slug}:
      rule: "Host(`{$domain}`)"
      entryPoints:
        - web
      service: {$slug}
  services:
    {$slug}:
      loadBalancer:
        servers:
          - url: "http://{$slug}_nginx"
YAML;
    }
}
