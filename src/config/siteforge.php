<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'projects'  => env('PROJECTS_BASE_PATH', '/opt/siteforge/projects'),
        'templates' => env('TEMPLATES_BASE_PATH', '/opt/siteforge/templates'),
        'backups'   => env('BACKUP_PATH', '/opt/siteforge/backups'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Docker
    |--------------------------------------------------------------------------
    */
    'docker' => [
        'socket'          => env('DOCKER_SOCKET', '/var/run/docker.sock'),
        'compose_timeout' => env('DOCKER_COMPOSE_TIMEOUT', 300),
        'project_prefix'  => 'sf_', // prefisso per i nomi compose
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'disk'           => env('BACKUP_DISK', 'local'),
        'keep_last'      => env('BACKUP_KEEP_LAST', 10),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    */
    'health_check' => [
        'http_timeout'     => 10, // secondi
        'interval_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | License (stub)
    |--------------------------------------------------------------------------
    */
    'license' => [
        'key'     => env('LICENSE_KEY', null),
        'enabled' => false, // disabilitato: tutte le feature sempre abilitate
    ],

];
