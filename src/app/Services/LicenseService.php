<?php

namespace App\Services;

/**
 * LicenseService - Stub per future implementazioni licensing.
 *
 * Attualmente tutte le feature sono sempre abilitate.
 * In futuro questo servizio verificherà licenze, limiti di progetti, ecc.
 */
class LicenseService
{
    public function canCreateProject(): bool
    {
        return true;
    }

    public function canUseFeature(string $feature): bool
    {
        return true;
    }

    public function canCreateServer(): bool
    {
        return true;
    }

    public function canUseBackups(): bool
    {
        return true;
    }

    public function canUseCloning(): bool
    {
        return true;
    }

    public function canUseHealthChecks(): bool
    {
        return true;
    }

    public function getMaxProjects(): int
    {
        return PHP_INT_MAX;
    }

    public function getMaxServers(): int
    {
        return PHP_INT_MAX;
    }

    public function getLicenseInfo(): array
    {
        return [
            'plan'       => 'unlimited',
            'valid_until'=> null,
            'features'   => ['all'],
        ];
    }
}
