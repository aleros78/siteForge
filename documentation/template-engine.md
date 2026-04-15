# SiteForge — Template Engine

Il Template Engine è il sistema che trasforma i **file stub** (con placeholder) nei file reali di un progetto deployato.

---

## Concetti base

### Template

Un **Template** è un insieme di file stub che definisce l'intero stack di un tipo di progetto. È salvato nel database e gestibile dal pannello.

```
Template "Laravel Basic"
├── docker-compose.stub  →  docker-compose.yml
├── nginx.conf.stub      →  docker/nginx/default.conf
└── .env.stub            →  .env
```

### TemplateFile

Un **TemplateFile** è un singolo file all'interno di un template. Contiene:

- `content` — il testo del file con i placeholder `{{nome}}`
- `target_path` — dove verrà scritto il file nella directory del progetto
- `type` — `docker-compose` | `nginx` | `env` | `custom`

### Placeholder

I placeholder usano la sintassi `{{nome_variabile}}` e vengono sostituiti con i valori reali al momento del provisioning.

---

## Placeholder disponibili

### Automatici (sempre disponibili)

| Placeholder | Valore | Esempio |
|-------------|--------|---------|
| `{{project_slug}}` | Slug del progetto | `mio-sito` |
| `{{project_name}}` | Nome del progetto | `Mio Sito` |
| `{{project_domain}}` | Dominio principale | `mio-sito.example.com` |
| `{{compose_project_name}}` | Nome progetto Docker Compose | `sf_mio-sito` |
| `{{environment}}` | Ambiente | `production` |
| `{{app_env}}` | Valore per APP_ENV di Laravel | `production` |
| `{{app_debug}}` | Valore per APP_DEBUG | `false` |
| `{{app_key}}` | Chiave applicazione Laravel | `base64:Xyz...` |
| `{{db_database}}` | Nome database generato | `db_mio_sito` |
| `{{db_username}}` | Username DB generato | `u_mio_sito` |
| `{{db_password}}` | Password DB generata (random) | `a3f9bc2e1d...` |
| `{{db_root_password}}` | Password root DB (random) | `9e2d1a8c7f...` |

> `app_key`, `db_password` e `db_root_password` vengono **generati casualmente** ad ogni provisioning.  
> `db_database` e `db_username` vengono derivati dallo slug del progetto.

### Personalizzati (da `env_vars` del progetto)

Nel form di creazione progetto è possibile aggiungere variabili custom nella sezione **"Variabili di Ambiente"**.  
Queste sovrascrivono i placeholder automatici con lo stesso nome.

```
Progetto: mio-sito
env_vars:
  db_password = "mia_password_custom"
  APP_TIMEZONE = "Europe/Rome"
```

Nell'`.env.stub`:
```
DB_PASSWORD={{db_password}}      → DB_PASSWORD=mia_password_custom
APP_TIMEZONE={{APP_TIMEZONE}}    → APP_TIMEZONE=Europe/Rome
```

---

## Template built-in

### laravel-basic

Stack completo per applicazioni Laravel con tutti i servizi necessari.

**Servizi inclusi:**
- `app` — PHP 8.3-FPM per eseguire Laravel
- `nginx` — web server che espone il servizio su HTTP
- `db` — MariaDB 11.2 (con healthcheck)
- `redis` — cache e sessioni
- `queue` — worker per il queue system di Laravel

**File generati:**

| File stub | Destinazione |
|-----------|-------------|
| `docker-compose.stub` | `docker-compose.yml` |
| `nginx.conf.stub` | `docker/nginx/default.conf` |
| `.env.stub` | `.env` |

### php-basic

Stack minimale per applicazioni PHP senza framework.

**Servizi inclusi:** `app`, `nginx`, `db`

---

## Creare un template personalizzato

### 1. Dal pannello Filament

Vai su **Infrastruttura → Template → Nuovo Template** e compila:

- **Nome** e **Slug** (univoco)
- **Tipo** (laravel, php, nodejs, static, custom)
- **Versione**

Nella sezione **File Template**, aggiungi i file stub uno per uno:

```
Filename:     docker-compose.stub
Target path:  docker-compose.yml
Tipo:         docker-compose
Contenuto:    [incolla il contenuto con i placeholder]
```

### 2. Come file su filesystem (per template built-in)

Crea una directory in `templates/{tuo-template}/` con i seguenti file:

**`template.json`**
```json
{
    "name": "Il Mio Template",
    "slug": "il-mio-template",
    "description": "Descrizione del template",
    "version": "1.0.0",
    "type": "custom",
    "is_active": true,
    "is_builtin": true,
    "services": ["app", "nginx", "db"],
    "files": [
        {
            "filename": "docker-compose.stub",
            "target_path": "docker-compose.yml",
            "type": "docker-compose",
            "is_required": true
        }
    ]
}
```

**`docker-compose.stub`** (esempio minimo)
```yaml
version: '3.8'

networks:
  {{project_slug}}_network:
    driver: bridge
  traefik_public:
    external: true
    name: traefik_public

services:
  nginx:
    image: nginx:1.25-alpine
    container_name: {{compose_project_name}}_nginx
    restart: unless-stopped
    networks:
      - {{project_slug}}_network
      - traefik_public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik_public"
      - "traefik.http.routers.{{compose_project_name}}.rule=Host(`{{project_domain}}`)"
      - "traefik.http.routers.{{compose_project_name}}.entrypoints=web"
      - "traefik.http.services.{{compose_project_name}}.loadbalancer.server.port=80"
```

Poi aggiungi il template al seeder o caricalo tramite il pannello.

---

## Regole per scrivere file stub

### Naming dei container

Usa sempre `{{compose_project_name}}` come prefisso dei nomi container:

```yaml
container_name: {{compose_project_name}}_nginx   # ✅
container_name: mio-nginx                         # ❌ fisso, causerebbe conflitti
```

### Naming delle reti interne

Usa `{{project_slug}}_network` per la rete interna del progetto:

```yaml
networks:
  {{project_slug}}_network:
    driver: bridge
```

### Naming dei volumi

Usa `{{project_slug}}` come prefisso per evitare conflitti tra progetti:

```yaml
volumes:
  {{project_slug}}_db_data:
    driver: local
```

### Rete Traefik

Il container che espone il servizio HTTP **deve** essere connesso a entrambe le reti:

```yaml
services:
  nginx:
    networks:
      - {{project_slug}}_network  # rete interna
      - traefik_public            # rete condivisa con Traefik
```

### Label Traefik obbligatorie

Il container nginx (o il servizio che risponde su HTTP) deve avere queste label:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.docker.network=traefik_public"
  - "traefik.http.routers.{{compose_project_name}}.rule=Host(`{{project_domain}}`)"
  - "traefik.http.routers.{{compose_project_name}}.entrypoints=web"
  - "traefik.http.services.{{compose_project_name}}.loadbalancer.server.port=80"
```

---

## Logica di rendering

Il metodo `TemplateFile::renderWith(array $vars)` esegue una sostituzione semplice:

```php
public function renderWith(array $vars): string
{
    $content = $this->content;
    foreach ($vars as $key => $value) {
        $content = str_replace('{{' . $key . '}}', (string) $value, $content);
    }
    return $content;
}
```

**Comportamento:**
- I placeholder non trovati in `$vars` vengono lasciati invariati nel file generato
- La sostituzione è case-sensitive: `{{project_slug}}` ≠ `{{Project_Slug}}`
- I valori sono convertiti a stringa prima della sostituzione

---

## Gestione valori sensibili

Le password generate vengono salvate nel campo `env_vars` del progetto (JSON, cifrato a riposo se si configura `SESSION_ENCRYPT=true`).

Questo permette di:
- Riutilizzare le stesse credenziali in caso di redeploy
- Mostrare le credenziali all'utente dal pannello (sezione env_vars)
- Non rigenerare password ad ogni redeploy (il DB del progetto contiene già i dati)
