<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LicenseService;
use App\Services\DockerService;
use App\Services\TemplateEngineService;
use App\Services\ProvisionProjectService;
use App\Services\BackupService;
use App\Services\HealthCheckService;
use App\Services\AuditService;
use App\Services\TraefikService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LicenseService::class);
        $this->app->singleton(DockerService::class);
        $this->app->singleton(TemplateEngineService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(TraefikService::class);

        $this->app->bind(ProvisionProjectService::class);
        $this->app->bind(BackupService::class);
        $this->app->bind(HealthCheckService::class);
    }

    public function boot(): void
    {
        //
    }
}
