# SiteForge — Architettura

## Stack tecnologico

| Layer | Tecnologia | Versione |
|-------|-----------|----------|
| Framework backend | Laravel | 11 |
| Admin panel | Filament | 3.x |
| Database manager | MariaDB | 11.2 |
| Cache / sessioni | Database (Laravel) | — |
| Queue | Database driver | — |
| Web server (manager) | Nginx | 1.25 |
| PHP | PHP-FPM | 8.3 |
| Reverse proxy | Traefik | 3.0 |
| Container runtime | Docker + Compose | v2 |

---

## Struttura del repository

```
ProgettoServer/
│
├── docker-compose.yml          # Stack del manager SiteForge
├── Makefile                    # Shortcut comandi
├── .env.example                # Variabili d'ambiente del manager
│
├── docker/
│   ├── php/
│   │   ├── Dockerfile          # Immagine PHP (stage: production / development)
│   │   └── php.ini             # Configurazione PHP
│   ├── nginx/
│   │   ├── nginx.conf          # Config globale Nginx
│   │   └── default.conf        # Virtual host del pannello
│   ├── traefik/
│   │   ├── traefik.yml         # Configurazione Traefik
│   │   ├── acme.json           # Certificati Let's Encrypt (generato)
│   │   └── dynamic/            # Config dinamiche Traefik
│   └── entrypoint.sh           # Script di avvio container app
│
├── src/                        # Applicazione Laravel
│   ├── app/
│   │   ├── Models/             # Modelli Eloquent
│   │   ├── Services/           # Business logic
│   │   ├── Jobs/               # Job asincroni
│   │   ├── Filament/
│   │   │   ├── Resources/      # Pannello admin
│   │   │   └── Widgets/        # Widget dashboard
│   │   └── Providers/
│   ├── config/
│   │   ├── siteforge.php       # Configurazione custom
│   │   ├── database.php
│   │   ├── queue.php
│   │   └── logging.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   ├── web.php
│   │   └── console.php         # Scheduled tasks
│   └── resources/views/
│       └── filament/           # Blade views custom
│
└── templates/                  # Template built-in
    └── laravel-basic/
        ├── docker-compose.stub
        ├── nginx.conf.stub
        ├── .env.stub
        └── template.json
```

---

## Container del manager

```
┌─────────────────────────────────────────────────────────┐
│                    Host Linux                            │
│                                                         │
│  ┌──────────┐    ┌──────────────────────────────────┐  │
│  │ Traefik  │───▶│          traefik_public           │  │
│  │ :80/:443 │    │         (Docker network)          │  │
│  └──────────┘    └──────┬───────────────────┬────────┘  │
│       │                 │                   │            │
│  ┌────▼────┐    ┌───────▼──────┐   ┌───────▼──────┐    │
│  │  Nginx  │    │  siteforge   │   │  progetto-a  │    │
│  │(manager)│    │   (futuro)   │   │   (nginx)    │    │
│  └────┬────┘    └──────────────┘   └──────────────┘    │
│       │                                                  │
│  ┌────▼────────────────────────────────────────┐        │
│  │              siteforge_internal              │        │
│  │                (Docker network)              │        │
│  │  ┌─────┐  ┌─────────┐  ┌───────┐  ┌──────┐ │        │
│  │  │ App │  │ MariaDB │  │ Redis │  │Queue │ │        │
│  │  │(FPM)│  │(healthy)│  │       │  │Worker│ │        │
│  │  └─────┘  └─────────┘  └───────┘  └──────┘ │        │
│  └────────────────────────────────────────────┘        │
│                                                         │
│  /var/run/docker.sock ──────────────────────────────▶  │
│  (montato nei container app, queue, scheduler)          │
└─────────────────────────────────────────────────────────┘
```

---

## Architettura applicativa

SiteForge segue una **architettura a layer** con separazione netta tra:

```
HTTP Request / Azione Filament
        │
        ▼
┌───────────────┐
│   Controller  │  (minima logica, solo orchestrazione)
│   / Page      │
└───────┬───────┘
        │
        ▼
┌───────────────┐
│    Service    │  (business logic pura, testabile)
│    Layer      │
└───────┬───────┘
        │
        ▼
┌───────────────┐
│   Job Queue   │  (operazioni async lunghe: provisioning, backup, ...)
└───────┬───────┘
        │
        ▼
┌───────────────┐
│ DockerService │  (wrapper Symfony Process → docker compose)
└───────┬───────┘
        │
        ▼
    Docker API (via socket /var/run/docker.sock)
```

### Principi guida

- **No logica nei controller** — le pagine Filament orchestrano, i Service eseguono
- **Ogni operazione è tracciata** — `AuditService` registra start/output/errore/durata
- **No shell libera** — `DockerService` usa solo comandi predefiniti via `Symfony\Process`
- **Job per operazioni lente** — provisioning, backup e health check sono sempre asincroni
- **Idempotenza** — i seeder non duplicano dati, le directory vengono create solo se assenti

---

## Schema del database

```
servers ──────────────────────────────────┐
  id, name, host, base_path,              │
  is_local, status, docker_version        │
                                          │
templates ────────────────────┐           │
  id, name, slug, type,       │           │
  is_active, is_builtin       │           │
                              │           │
template_files                │           │
  id, template_id ────────────┘           │
  filename, target_path,                  │
  content, type                           │
                                          │
projects ──────────────────────────────┐  │
  id, name, slug                       │  │
  server_id ──────────────────────────────┘
  template_id ─────────────────────────┘  │
  base_path, primary_domain              │
  environment, status                   │
  env_vars (JSON), meta (JSON)          │
  cloned_from_id (self-ref)             │
                                        │
project_domains                         │
  id, project_id ──────────────────────┘
  domain, is_primary, https_enabled     │
                                        │
audit_logs                              │
  id, project_id ──────────────────────┘
  action, status, output, error_output  │
  exit_code, triggered_by, context      │
                                        │
backups                                 │
  id, project_id ──────────────────────┘
  type, file_path, file_size            │
  status, disk, expires_at             │
                                        │
health_checks                           │
  id, project_id ──────────────────────┘
  status, containers_up, http_ok
  http_status_code, response_time_ms
  container_statuses (JSON)
```

---

## Flusso: creazione e deploy di un progetto

```
1. Utente compila form nel pannello
        │
        ▼
2. CreateProject::mutateFormDataBeforeCreate()
   → calcola base_path dal server selezionato
        │
        ▼
3. Record Project salvato nel DB (status: pending)
        │
        ▼
4. afterCreate() → dispatch ProvisionProjectJob
        │
        ▼
5. ProvisionProjectJob::handle()
   → chiama ProvisionProjectService::provision()
        │
        ├── a. Project status → "provisioning"
        │
        ├── b. Crea directory su filesystem host
        │      base_path/{slug}/
        │      base_path/{slug}/docker/nginx/
        │      base_path/{slug}/backups/
        │      base_path/{slug}/logs/
        │
        ├── c. TemplateEngineService::generateFiles()
        │      → legge TemplateFile dal DB
        │      → sostituisce {{placeholder}} con valori reali
        │      → scrive i file generati su disco
        │
        ├── d. ProjectDomain creato per il dominio primario
        │
        ├── e. DockerService::up(project)
        │      → docker compose -f .../docker-compose.yml -p sf_{slug} up -d
        │
        ├── f. sleep(3) per stabilizzazione container
        │
        ├── g. HealthCheckService::check(project)
        │      → verifica container up + HTTP response
        │
        └── h. Project status → "running"
             AuditLog → success
```

---

## Flusso: health check periodico

```
Scheduler (ogni minuto) → php artisan schedule:run
        │
        ▼
HealthCheckJob (ogni 5 minuti)
        │
        ▼
HealthCheckService::checkAll()
   → Project::where('status', 'running')->get()
        │
        ▼
Per ogni progetto:
   1. docker compose ps --format json
      → verifica container status
   2. Http::get("http://{domain}")
      → verifica risposta HTTP
   3. Calcola stato: ok / warning / error
   4. Salva HealthCheck nel DB
```

---

## Networking dei progetti gestiti

Ogni progetto viene creato con **due reti Docker**:

```
{slug}_network     → rete interna al progetto (app ↔ db ↔ redis)
traefik_public     → rete condivisa con Traefik per il routing HTTP
```

Solo il container `nginx` del progetto è connesso a `traefik_public`.  
Gli altri container (app, db, redis) sono isolati nella rete interna.

Le label Traefik sul container `nginx` del progetto:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.docker.network=traefik_public"
  - "traefik.http.routers.sf_{slug}.rule=Host(`{domain}`)"
  - "traefik.http.routers.sf_{slug}.entrypoints=web"
  - "traefik.http.services.sf_{slug}.loadbalancer.server.port=80"
```

Traefik rileva automaticamente i nuovi container tramite il **Docker provider** (watch mode attivo).
