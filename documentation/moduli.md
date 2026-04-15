# SiteForge — Moduli

## Indice moduli

1. [Server Management](#1-server-management)
2. [Template Engine](#2-template-engine)
3. [Project Management](#3-project-management)
4. [Provisioning Engine](#4-provisioning-engine)
5. [Docker Management](#5-docker-management)
6. [Domain & Traefik](#6-domain--traefik-integration)
7. [Backup System](#7-backup-system)
8. [Clone / Staging](#8-clone--staging)
9. [Health Check](#9-health-check-system)
10. [Audit Log](#10-audit-log)
11. [License Service](#11-license-service)

---

## 1. Server Management

**File:** `app/Models/Server.php`, `app/Filament/Resources/ServerResource.php`

Gestisce i server su cui vengono deployati i progetti. Nella fase iniziale è supportato solo il **server locale** (stesso host del manager). Il modello è predisposto per supportare server remoti via SSH in futuro.

### Campi principali

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `name` | string | Nome leggibile |
| `host` | string | IP o hostname (`localhost` per server locale) |
| `base_path` | string | Path base dove vengono creati i progetti |
| `is_local` | boolean | Se `true`, i comandi Docker vengono eseguiti localmente |
| `status` | enum | `online` / `offline` / `unknown` |
| `docker_version` | string | Versione Docker rilevata |
| `last_checked_at` | datetime | Ultima verifica di connettività |

### Azioni disponibili nel pannello

- **Crea server** — form con nome, host, path base
- **Verifica** — esegue `docker version` e aggiorna stato e versione
- **Modifica** — aggiorna le impostazioni
- **Elimina** — solo se non ha progetti associati

### Metodi utili (Model)

```php
$server->getProjectPath('mio-sito');
// Ritorna: /opt/siteforge/projects/mio-sito

$server->isOnline();
// Ritorna: bool
```

---

## 2. Template Engine

**File:** `app/Services/TemplateEngineService.php`, `app/Models/Template.php`, `app/Models/TemplateFile.php`

Il cuore del sistema di generazione file. Ogni **Template** è un insieme di file stub con placeholder che vengono sostituiti al momento del provisioning.

### Struttura dati

```
Template
  └── TemplateFile (1 per ogni file da generare)
        ├── filename       → nome del file stub (es: docker-compose.stub)
        ├── target_path    → path relativo nel progetto (es: docker-compose.yml)
        ├── type           → docker-compose | nginx | env | custom
        ├── content        → contenuto con {{placeholder}}
        └── is_required    → se mancante blocca il provisioning
```

### Placeholder disponibili

| Placeholder | Esempio di valore |
|-------------|-------------------|
| `{{project_slug}}` | `mio-sito` |
| `{{project_name}}` | `Mio Sito` |
| `{{project_domain}}` | `mio-sito.example.com` |
| `{{compose_project_name}}` | `sf_mio-sito` |
| `{{environment}}` | `production` |
| `{{app_key}}` | `base64:...` (generato) |
| `{{app_env}}` | `production` |
| `{{app_debug}}` | `false` |
| `{{db_database}}` | `db_mio_sito` (derivato dallo slug) |
| `{{db_username}}` | `u_mio_sito` (derivato dallo slug) |
| `{{db_password}}` | `a3f8bc...` (generato casuale) |
| `{{db_root_password}}` | `9e2d1a...` (generato casuale) |

I valori in `env_vars` del progetto **sovrascrivono** i placeholder generati automaticamente.

### Come vengono generati i file

```php
// In TemplateEngineService::generateFiles()
$placeholders = $this->getPlaceholders($project);

foreach ($template->files as $file) {
    $targetPath = $project->base_path . '/' . $file->target_path;
    $content    = $file->renderWith($placeholders); // str_replace dei {{placeholder}}
    file_put_contents($targetPath, $content);
}
```

### Template built-in

| Nome | Slug | Servizi inclusi |
|------|------|----------------|
| Laravel Basic | `laravel-basic` | app (PHP-FPM), nginx, db (MariaDB), redis, queue |
| PHP Basic | `php-basic` | app (PHP-FPM), nginx, db (MariaDB) |

---

## 3. Project Management

**File:** `app/Models/Project.php`, `app/Filament/Resources/ProjectResource.php`

Il modulo centrale del sistema. Un **Project** rappresenta un'applicazione deployata su un server.

### Campi principali

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `name` | string | Nome leggibile |
| `slug` | string | Identificatore univoco (usato per nomi cartella e container) |
| `server_id` | FK | Server su cui è deployato |
| `template_id` | FK | Template usato per la generazione |
| `base_path` | string | Path assoluto della directory del progetto sull'host |
| `primary_domain` | string | Dominio principale (es: `mio-sito.example.com`) |
| `environment` | enum | `production` / `staging` / `development` |
| `status` | enum | Vedi tabella sotto |
| `env_vars` | JSON | Variabili d'ambiente custom |
| `meta` | JSON | Dati extra (porte assegnate, ecc.) |
| `cloned_from_id` | FK | Progetto sorgente se è un clone |

### Stati del progetto

| Stato | Descrizione |
|-------|-------------|
| `pending` | Appena creato, in attesa di provisioning |
| `provisioning` | Provisioning in corso |
| `running` | Container attivi e raggiungibili |
| `stopped` | Container fermi (`docker compose stop`) |
| `error` | Errore durante un'operazione |
| `rebuilding` | Redeploy con rebuild in corso |
| `cloning` | Clonazione da un progetto sorgente |

### Convenzioni di naming

Dato il progetto con slug `mio-sito`:

| Elemento | Valore |
|----------|--------|
| Directory | `/opt/siteforge/projects/mio-sito/` |
| Compose project | `sf_mio-sito` |
| Nomi container | `sf_mio-sito_app`, `sf_mio-sito_nginx`, ... |
| Nomi volume | `sf_mio-sito_db_data` |

### Metodi utili (Model)

```php
$project->isRunning();           // true se status === 'running'
$project->isStopped();           // true se status === 'stopped'
$project->isProvisioning();      // true se status in ['provisioning', 'rebuilding', 'cloning']
$project->hasError();            // true se status === 'error'
$project->getDockerComposePath();// /opt/.../docker-compose.yml
$project->getComposeProjectName();// sf_mio-sito
$project->getEnvPath();          // /opt/.../.env
```

---

## 4. Provisioning Engine

**File:** `app/Services/ProvisionProjectService.php`, `app/Jobs/ProvisionProjectJob.php`

Crea fisicamente il progetto su disco e avvia lo stack Docker.

### Step del provisioning

```
1. project.status → "provisioning"
2. Crea struttura directory:
   {base_path}/
   {base_path}/docker/nginx/
   {base_path}/backups/
   {base_path}/logs/
3. TemplateEngineService::generateFiles() → scrive i file su disco
4. ProjectDomain creato per il dominio primario
5. DockerService::up(project) → docker compose up -d
6. sleep(3)
7. HealthCheckService::check(project)
8. project.status → "running" (o "error" se health check fallisce)
9. AuditLog registrato
```

### Gestione errori

Se qualsiasi step fallisce:
- Il progetto viene marcato `status = error`
- L'errore viene salvato nell'`AuditLog`
- Il `ProvisionProjectJob` non ha retry automatici (il provisioning non è idempotente)
- L'utente può correggere e fare **redeploy** manualmente

### Redeploy

Il metodo `redeploy()` rigenera i file di configurazione ed esegue `docker compose up --build`:

```
1. project.status → "rebuilding"
2. TemplateEngineService::generateFiles() → riscrive i file
3. DockerService::up(project, build: true) → docker compose up -d --build
4. project.status → "running"
```

---

## 5. Docker Management

**File:** `app/Services/DockerService.php`

Wrapper attorno a `Symfony\Component\Process` per eseguire comandi Docker Compose in modo sicuro. **Non viene mai eseguita shell libera**: tutti i comandi sono array espliciti di argomenti.

### Metodi principali

```php
$docker->up(Project $project, bool $build = false): array
$docker->down(Project $project, bool $removeVolumes = false): array
$docker->start(Project $project): array
$docker->stop(Project $project): array
$docker->restart(Project $project): array
$docker->build(Project $project): array
$docker->pull(Project $project): array
$docker->logs(Project $project, int $lines = 100, ?string $service = null): array
$docker->ps(Project $project): array
$docker->isRunning(Project $project): bool
$docker->getContainerStatuses(Project $project): array
$docker->version(): array
$docker->isDockerAvailable(): bool
```

### Formato risposta

Tutti i metodi ritornano un array uniforme:

```php
[
    'success'   => bool,
    'exit_code' => int,
    'output'    => string,
    'error'     => string,
]
```

### Timeout

| Operazione | Timeout |
|------------|---------|
| Operazioni standard (start, stop, restart) | 60s |
| Deploy / build (`up`, `build`, `pull`) | 300s |
| Log | 30s |

### Sicurezza

Il comando viene costruito come array e passato a `Symfony\Process`, evitando qualsiasi interpolazione di stringa che potrebbe aprire a command injection:

```php
// ✅ Sicuro
$command = ['docker', 'compose', '-f', $path, '-p', $name, 'up', '-d'];
new Process($command, $cwd);

// ❌ Non usato
shell_exec("docker compose -f {$path} up -d");
```

---

## 6. Domain & Traefik Integration

**File:** `app/Services/TraefikService.php`, `app/Models/ProjectDomain.php`

Gestisce il mapping domini ↔ container tramite le label Docker di Traefik.

### Come funziona il routing

Traefik legge continuamente le label dei container via Docker socket. Quando un nuovo container viene avviato con le label corrette, il routing è automatico senza bisogno di riavviare Traefik.

### Label generate per ogni progetto

```yaml
# Label sul container nginx del progetto
- "traefik.enable=true"
- "traefik.docker.network=traefik_public"
- "traefik.http.routers.sf_{slug}.rule=Host(`{domain}`)"
- "traefik.http.routers.sf_{slug}.entrypoints=web"
- "traefik.http.services.sf_{slug}.loadbalancer.server.port=80"
```

### Domini multipli

Ogni progetto può avere più domini via la tabella `project_domains`. Il dominio primario è quello usato nelle label principali; i domini aggiuntivi generano router Traefik separati che puntano allo stesso servizio.

### HTTPS (configurazione futura)

Per abilitare HTTPS automatico con Let's Encrypt, decommentare nel file `docker/traefik/traefik.yml`:

```yaml
certificatesResolvers:
  letsencrypt:
    acme:
      email: admin@yourdomain.com
      storage: /etc/traefik/acme.json
      httpChallenge:
        entryPoint: web
```

E aggiornare `TraefikService::generateLabels()` per aggiungere le label TLS.

---

## 7. Backup System

**File:** `app/Services/BackupService.php`, `app/Jobs/BackupProjectJob.php`, `app/Models/Backup.php`

Esegue backup del database tramite `mysqldump` eseguito nel container `db` del progetto.

### Tipi di backup

| Tipo | Descrizione | Stato |
|------|-------------|-------|
| `database` | Dump SQL compresso (gzip) del database del progetto | Implementato |
| `files` | Archivio dei file del progetto | Predisposto |
| `full` | Database + files | Predisposto |

### Processo backup database

```
1. Backup::create(status: 'running')
2. Crea directory storage/app/backups/{slug}/
3. Recupera credenziali DB da project.env_vars
4. docker compose exec -T db mysqldump ... → output SQL
5. gzip dell'output
6. Salva file: db_{slug}_YYYYMMDD_HHmmss.sql.gz
7. Backup::update(status: 'completed', file_path, file_size)
8. project.last_backed_up_at → now()
9. Pruning: elimina backup oltre la soglia (default: ultimi 10)
```

### Storage

I backup vengono salvati in `storage/app/backups/{project_slug}/`.  
La path è configurabile via `BACKUP_PATH` nel `.env`.

### Retention

Per default vengono mantenuti gli **ultimi 10 backup completati** per progetto.  
I file fisici vengono eliminati insieme al record DB.

Il campo `expires_at` su ogni record è impostato a **30 giorni** dalla creazione.

---

## 8. Clone / Staging

**File:** `app/Services/CloneProjectService.php`

Permette di duplicare un progetto esistente con un nuovo slug e dominio. Utile per creare ambienti di staging da un progetto di produzione.

### Processo di clonazione

```
1. Verifica che il nuovo slug non esista già
2. Crea nuovo record Project con:
   - stessi server_id, template_id
   - base_path calcolato dal nuovo slug
   - nuovo dominio primario
   - env_vars copiati dal sorgente
   - cloned_from_id = sorgente.id
   - status: 'cloning'
3. ProvisionProjectService::provision(clone)
   (provisioning completo del clone)
```

### Limitazioni attuali

- La copia del database non è ancora implementata (struttura predisposta)
- I file dell'applicazione (codice sorgente) non vengono copiati automaticamente

---

## 9. Health Check System

**File:** `app/Services/HealthCheckService.php`, `app/Jobs/HealthCheckJob.php`, `app/Models/HealthCheck.php`

Verifica periodicamente che i progetti siano operativi.

### Metriche verificate

| Metrica | Come viene verificata |
|---------|----------------------|
| `containers_up` | `docker compose ps --filter status=running` — tutti i container devono essere in running |
| `port_responding` | Incluso nel check HTTP |
| `http_ok` | `Http::get("http://{domain}")` — risposta < 500 |
| `http_status_code` | Codice HTTP ricevuto |
| `response_time_ms` | Tempo di risposta in millisecondi |

### Logica di stato

| Condizione | Stato |
|------------|-------|
| Tutti i container up + HTTP OK | `ok` |
| Tutti i container up ma HTTP non risponde | `warning` |
| Uno o più container non in running | `error` |

### Frequenza

Il job `HealthCheckJob` viene schedulato ogni **5 minuti** tramite il Laravel Scheduler nel file `routes/console.php`.

---

## 10. Audit Log

**File:** `app/Services/AuditService.php`, `app/Models/AuditLog.php`

Ogni operazione eseguita su un progetto viene tracciata automaticamente.

### Informazioni registrate

| Campo | Descrizione |
|-------|-------------|
| `project_id` | Progetto interessato (nullable per operazioni di sistema) |
| `action` | Tipo operazione: `provision`, `start`, `stop`, `restart`, `backup`, `redeploy`, `clone` |
| `status` | `pending` → `running` → `success` / `failed` |
| `output` | Output testuale del comando |
| `error_output` | Stderr in caso di errore |
| `exit_code` | Codice di uscita del processo |
| `triggered_by` | Email dell'utente o `system` se schedulato |
| `context` | JSON con dati extra specifici dell'operazione |
| `started_at` | Timestamp inizio |
| `completed_at` | Timestamp fine |
| `duration` | Durata in secondi (calcolata) |

### Utilizzo del service

```php
// Pattern standard usato in tutti i Service/Job

$log = $this->audit->start('provision', $project);

try {
    // ... esecuzione operazione ...
    $this->audit->success($log, $output);
} catch (RuntimeException $e) {
    $this->audit->fail($log, $e->getMessage());
    throw $e;
}
```

---

## 11. License Service

**File:** `app/Services/LicenseService.php`

**Stub** per il futuro sistema di licensing commerciale. Attualmente **tutte le feature sono sempre abilitate** e nessun limite viene applicato.

### API pubblica

```php
$license = app(LicenseService::class);

$license->canCreateProject();   // → true (sempre)
$license->canCreateServer();    // → true (sempre)
$license->canUseFeature('backup');  // → true (sempre)
$license->canUseBackups();      // → true (sempre)
$license->canUseCloning();      // → true (sempre)
$license->canUseHealthChecks(); // → true (sempre)
$license->getMaxProjects();     // → PHP_INT_MAX
$license->getMaxServers();      // → PHP_INT_MAX
$license->getLicenseInfo();     // → ['plan' => 'unlimited', ...]
```

### Evoluzione futura

Quando verrà implementato il licensing reale, sarà sufficiente modificare questa classe per:

- Verificare una chiave di licenza via API esterna
- Leggere i limiti del piano acquistato
- Bloccare le feature non incluse nel piano
- Gestire la scadenza della licenza

Il resto del codice non dovrà cambiare, in quanto tutte le verifiche passano da questo service.
