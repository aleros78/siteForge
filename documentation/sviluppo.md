# SiteForge — Guida allo Sviluppo

## Ambiente di sviluppo

### Avvio in modalità development

```bash
# Cambia il target nel docker-compose.yml (o usa override)
# Dockerfile target: development include Xdebug e dipendenze dev

cp src/.env.example src/.env
# Imposta APP_ENV=local, APP_DEBUG=true

docker compose build
docker compose up -d
```

### Shell nel container

```bash
make shell          # accesso come www-data
make shell-root     # accesso come root
```

### Comandi Artisan frequenti

```bash
make artisan CMD="route:list"
make artisan CMD="queue:work --verbose"
make artisan CMD="migrate:fresh --seed"
make artisan CMD="tinker"
```

---

## Struttura del codice

### Dove aggiungere nuova logica

| Tipo di logica | Dove metterla |
|---------------|---------------|
| Operazione su Docker | `app/Services/DockerService.php` |
| Operazione asincrona (>5s) | Nuovo Job in `app/Jobs/` |
| Operazione sincrona su progetto | Nuovo metodo in `ProvisionProjectService` o servizio dedicato |
| Logica di business pura | Nuovo Service in `app/Services/` |
| Nuova schermata admin | Nuova Page in `app/Filament/Resources/.../Pages/` |
| Nuova sezione in tabella | Nuovo RelationManager |

### Convenzioni

- I Service hanno dipendenze iniettate nel costruttore
- Ogni operazione che può fallire registra un `AuditLog`
- I Job non contengono logica: delegano al Service corrispondente
- Nessun `shell_exec`, `exec`, `system` — solo `Symfony\Process` con array di argomenti

---

## Aggiungere un nuovo comando Docker

**Esempio:** aggiungere il comando `docker compose pause`.

**1. Aggiungi il metodo a `DockerService`:**

```php
// app/Services/DockerService.php

public function pause(Project $project): array
{
    return $this->runCompose($project, ['pause']);
}

public function unpause(Project $project): array
{
    return $this->runCompose($project, ['unpause']);
}
```

**2. Crea i Job:**

```php
// app/Jobs/PauseProjectJob.php

class PauseProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(public readonly Project $project) {}

    public function handle(DockerService $docker, AuditService $audit): void
    {
        $log = $audit->start('pause', $this->project);
        $result = $docker->pause($this->project);

        if ($result['success']) {
            $this->project->update(['status' => 'stopped']); // o nuovo stato
            $audit->success($log, $result['output']);
        } else {
            $audit->fail($log, $result['error']);
            throw new \RuntimeException($result['error']);
        }
    }
}
```

**3. Aggiungi l'azione al pannello Filament:**

In `ProjectResource.php`, nella sezione `actions()`:

```php
Tables\Actions\Action::make('pause')
    ->label('Pausa')
    ->icon('heroicon-o-pause')
    ->color('warning')
    ->visible(fn ($record) => $record->isRunning())
    ->requiresConfirmation()
    ->action(function (Project $record) {
        PauseProjectJob::dispatch($record);
        Notification::make()->title('Pausa in corso...')->warning()->send();
    }),
```

---

## Aggiungere un nuovo tipo di backup

**Esempio:** backup dei file del progetto.

**1. Aggiungi il metodo a `BackupService`:**

```php
// app/Services/BackupService.php

public function backupFiles(Project $project): Backup
{
    $backup = Backup::create([
        'project_id' => $project->id,
        'type'       => 'files',
        'status'     => 'running',
        'disk'       => 'local',
    ]);

    $auditLog = $this->audit->start('backup', $project, context: ['type' => 'files']);

    try {
        $backupDir = storage_path("app/backups/{$project->slug}");
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        $filename = "files_{$project->slug}_" . now()->format('Ymd_His') . ".tar.gz";
        $filePath = "{$backupDir}/{$filename}";
        $sourceDir = $project->base_path . '/app';

        $result = $this->docker->run(
            ['tar', '-czf', $filePath, '-C', dirname($sourceDir), basename($sourceDir)],
            '/'
        );

        if (!$result['success']) {
            throw new RuntimeException("tar fallito: " . $result['error']);
        }

        $backup->update([
            'status'    => 'completed',
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'expires_at'=> now()->addDays(30),
        ]);

        $this->audit->success($auditLog, "File backup: {$filename}");

    } catch (RuntimeException $e) {
        $backup->update(['status' => 'failed', 'error_output' => $e->getMessage()]);
        $this->audit->fail($auditLog, $e->getMessage());
        throw $e;
    }

    return $backup->fresh();
}
```

**2. Aggiorna `BackupProjectJob`:**

```php
match ($this->type) {
    'database' => $backup->backupDatabase($this->project),
    'files'    => $backup->backupFiles($this->project),    // ← aggiungi
    'full'     => $this->runFullBackup($backup, $this->project),
    default    => throw new \InvalidArgumentException("Tipo backup non supportato: {$this->type}"),
};
```

---

## Aggiungere un nuovo template

Vedi la sezione dedicata in [Template Engine](template-engine.md#creare-un-template-personalizzato).

Per aggiungere un template built-in che venga caricato automaticamente all'installazione, aggiungi un metodo privato al seeder `BuiltinTemplatesSeeder`:

```php
// database/seeders/BuiltinTemplatesSeeder.php

public function run(): void
{
    $this->seedLaravelBasic();
    $this->seedPhpBasic();
    $this->seedNodejsBasic(); // ← aggiungi chiamata
}

private function seedNodejsBasic(): void
{
    $template = Template::create([
        'name'       => 'Node.js Basic',
        'slug'       => 'nodejs-basic',
        'type'       => 'nodejs',
        'is_builtin' => true,
        // ...
    ]);

    TemplateFile::create([
        'template_id' => $template->id,
        'filename'    => 'docker-compose.stub',
        'target_path' => 'docker-compose.yml',
        'type'        => 'docker-compose',
        'content'     => $this->readStub('nodejs-basic/docker-compose.stub'),
    ]);
}
```

---

## Aggiungere un nuovo stato al progetto

**1. Aggiorna la migration** (o crea una nuova):

```php
// Modifica l'enum nella colonna 'status'
$table->enum('status', [
    'pending', 'provisioning', 'running', 'stopped',
    'error', 'rebuilding', 'cloning',
    'paused',  // ← nuovo stato
]);
```

**2. Aggiungi l'helper al Model:**

```php
// app/Models/Project.php

public function isPaused(): bool
{
    return $this->status === 'paused';
}
```

**3. Aggiorna `getStatusColorAttribute()`:**

```php
return match ($this->status) {
    'running'  => 'success',
    'stopped'  => 'warning',
    'paused'   => 'gray',     // ← nuovo
    'error'    => 'danger',
    // ...
};
```

**4. Aggiorna i colori nel Filament Resource** (`ProjectResource.php`).

---

## Estendere il pannello Filament

### Aggiungere un widget alla dashboard

```php
// app/Filament/Widgets/MioWidget.php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MioWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        return [
            Stat::make('La mia metrica', 42)
                ->description('Descrizione')
                ->color('success'),
        ];
    }
}
```

Registra il widget in `AdminPanelProvider`:

```php
->widgets([
    \App\Filament\Widgets\StatsOverviewWidget::class,
    \App\Filament\Widgets\ProjectsTableWidget::class,
    \App\Filament\Widgets\MioWidget::class,  // ← aggiungi
])
```

### Aggiungere una pagina custom

```php
// app/Filament/Pages/SystemStatus.php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SystemStatus extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationGroup = 'Sistema';
    protected static string $view = 'filament.pages.system-status';

    public function getTitle(): string
    {
        return 'Stato del Sistema';
    }
}
```

---

## Testing

### Struttura test consigliata

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── TemplateEngineServiceTest.php
│   │   ├── DockerServiceTest.php
│   │   └── LicenseServiceTest.php
│   └── Models/
│       └── ProjectTest.php
└── Feature/
    ├── ProjectProvisioningTest.php
    └── BackupTest.php
```

### Esempio test unitario per TemplateEngineService

```php
// tests/Unit/Services/TemplateEngineServiceTest.php

use App\Models\Project;
use App\Models\Server;
use App\Models\Template;
use App\Services\TemplateEngineService;

it('sostituisce i placeholder correttamente', function () {
    $service = new TemplateEngineService();

    $project = Project::factory()->make([
        'slug'           => 'test-progetto',
        'name'           => 'Test Progetto',
        'primary_domain' => 'test.example.com',
        'environment'    => 'production',
    ]);

    $placeholders = $service->getPlaceholders($project);

    expect($placeholders['project_slug'])->toBe('test-progetto');
    expect($placeholders['project_domain'])->toBe('test.example.com');
    expect($placeholders['compose_project_name'])->toBe('sf_test-progetto');
    expect($placeholders['app_env'])->toBe('production');
    expect($placeholders['app_debug'])->toBe('false');
});
```

---

## Configurazione avanzata

### Variabili d'ambiente del manager (`src/.env`)

| Variabile | Default | Descrizione |
|-----------|---------|-------------|
| `PROJECTS_BASE_PATH` | `/opt/siteforge/projects` | Path host dove vengono creati i progetti |
| `TEMPLATES_BASE_PATH` | `/opt/siteforge/templates` | Path host dei template |
| `BACKUP_PATH` | `/opt/siteforge/backups` | Path host dei backup |
| `DOCKER_SOCKET` | `/var/run/docker.sock` | Path del socket Docker |
| `QUEUE_CONNECTION` | `database` | Driver queue (`database` o `redis`) |
| `ADMIN_EMAIL` | `admin@siteforge.local` | Email admin al primo avvio |
| `ADMIN_PASSWORD` | `SiteForge2024!` | Password admin al primo avvio |

### Configurazione `config/siteforge.php`

```php
return [
    'paths' => [
        'projects'  => env('PROJECTS_BASE_PATH', '/opt/siteforge/projects'),
        'templates' => env('TEMPLATES_BASE_PATH', '/opt/siteforge/templates'),
        'backups'   => env('BACKUP_PATH', '/opt/siteforge/backups'),
    ],
    'docker' => [
        'compose_timeout' => 300,  // secondi max per docker compose up
        'project_prefix'  => 'sf_',
    ],
    'backup' => [
        'keep_last'      => 10,    // ultimi N backup da mantenere per progetto
        'retention_days' => 30,    // giorni prima della scadenza
    ],
    'health_check' => [
        'http_timeout'     => 10,  // secondi timeout HTTP
        'interval_minutes' => 5,   // frequenza check
    ],
];
```

---

## Roadmap tecnica

### Prossimi sviluppi suggeriti

| Feature | Priorità | Note |
|---------|----------|------|
| HTTPS automatico (Let's Encrypt) | Alta | Decommentare config Traefik ACME |
| Supporto server remoti SSH | Alta | `DockerService` già predisposto |
| Copia database nel clone | Media | Struttura `CloneProjectService` pronta |
| Backup file applicazione | Media | `BackupService::backupFiles()` da implementare |
| Notifiche (email/Slack) su errori | Media | — |
| Sistema licensing reale | Media | `LicenseService` già stub |
| API REST pubblica | Bassa | Per integrazioni CI/CD |
| Multi-tenancy (organizzazioni) | Bassa | Richiede refactor models |
| Supporto Node.js, Python | Bassa | Aggiungere template |
| Metriche CPU/RAM container | Bassa | `docker stats` già accessibile via DockerService |
