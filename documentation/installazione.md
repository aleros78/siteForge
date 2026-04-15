# SiteForge — Guida all'Installazione

## Requisiti

| Requisito | Versione minima | Note |
|-----------|----------------|------|
| Sistema operativo | Ubuntu 20.04 LTS | Consigliato: Ubuntu 22.04 o 24.04 |
| RAM | 2 GB | 4 GB consigliati in produzione |
| Disco | 20 GB liberi | Per immagini Docker, progetti e backup |
| Accesso | `sudo` o root | Necessario per installare Docker e configurare il firewall |

> Docker, Docker Compose, la rete condivisa e il firewall vengono installati e configurati **automaticamente** dallo script `install.sh`. Non è necessario installarli manualmente.

---

## Installazione rapida (consigliata)

### 1. Clona il repository

```bash
git clone <url-repository> /opt/siteforge
cd /opt/siteforge
```

> Non è necessario il `sudo` per clonare il repository.

### 2. Esegui lo script di installazione

```bash
sudo bash install.sh
```

Lo script guiderà l'utente con alcune domande interattive (dominio, email admin, password) e si occuperà di tutto il resto in modo automatico e ripetibile.

---

## Cosa fa `install.sh`

Lo script esegue i seguenti passi nell'ordine:

| Passo | Operazione | Richiede sudo |
|-------|-----------|:-------------:|
| 1 | Verifica Ubuntu 20.04+ | — |
| 2 | `apt-get update && apt-get upgrade` | ✓ |
| 3 | Installa pacchetti base (`curl`, `git`, `ufw`, ecc.) | ✓ |
| 4 | Installa Docker CE dal repository ufficiale (se assente) | ✓ |
| 5 | Aggiunge l'utente corrente al gruppo `docker` | ✓ |
| 6 | Verifica Docker Compose v2 | — |
| 7 | Crea la rete Docker `traefik_public` | — |
| 8 | Crea le directory dati (`/opt/siteforge/projects`, ecc.) | ✓ |
| 9 | Crea `src/.env` da `.env.example` con valori interattivi | — |
| 10 | Genera password DB sicure con `openssl rand` | — |
| 11 | Crea e imposta i permessi di `acme.json` (Traefik) | ✓ |
| 12 | Imposta permessi su `storage/` e `bootstrap/cache/` | ✓ |
| 13 | `docker compose build --no-cache` | — |
| 14 | `docker compose up -d` | — |
| 15 | Attende che MariaDB sia pronto (healthcheck) | — |
| 16 | Verifica lo stato di tutti i container | — |
| 17 | Configura UFW: apre porte 80 e 443, blocca 8080 | ✓ |
| 18 | Stampa riepilogo con URL e credenziali | — |

All'avvio dei container, l'`entrypoint.sh` esegue automaticamente:
- migrazione del database (`php artisan migrate`)
- seeding iniziale (utente admin, server locale, template built-in)

---

## Configurazione interattiva

Durante l'esecuzione di `install.sh`, lo script chiede:

```
Dominio del pannello SiteForge [siteforge.localhost]:
Email amministratore [admin@siteforge.local]:
Password amministratore (min. 8 caratteri):
```

Premi **INVIO** per accettare il valore predefinito tra parentesi.  
Le password del database vengono generate automaticamente con `openssl rand -hex`.

> Se `src/.env` esiste già (installazione precedente o configurazione manuale), lo script lo salta senza sovrascriverlo.

---

## Primo accesso

Al termine dell'installazione, lo script stampa l'URL e le credenziali:

```
Pannello di controllo:
  URL:      http://<tuo-dominio>/admin
  Email:    <admin-email>
  Password: (quella impostata durante l'installazione)
```

Apri il browser e accedi al pannello. **Cambia la password** dal menu utente dopo il primo accesso.

---

## Installazione manuale (alternativa)

Se preferisci eseguire ogni passo separatamente, o se lo script automatico non è adatto al tuo ambiente:

### Requisiti da installare manualmente

```bash
# Aggiorna il sistema
sudo apt-get update && sudo apt-get upgrade -y

# Pacchetti base
sudo apt-get install -y ca-certificates curl gnupg lsb-release git

# Docker (repository ufficiale)
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
    https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
    | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Aggiungi il tuo utente al gruppo docker (richiede logout/login per avere effetto)
sudo usermod -aG docker $USER
```

### Setup progetto

```bash
# Rete Docker condivisa
docker network create traefik_public

# Directory dati
sudo mkdir -p /opt/siteforge/projects /opt/siteforge/backups /opt/siteforge/templates
sudo chown -R $USER:$USER /opt/siteforge

# Copia template built-in
cp -r templates/. /opt/siteforge/templates/

# Configurazione ambiente
cp src/.env.example src/.env
# Modifica src/.env con i tuoi valori

# File ACME per Traefik (permessi obbligatori 600)
touch docker/traefik/acme.json
sudo chmod 600 docker/traefik/acme.json

# Permessi storage Laravel
sudo chmod -R 775 src/storage src/bootstrap/cache
sudo chown -R $USER:www-data src/storage src/bootstrap/cache

# Build e avvio
docker compose build
docker compose up -d
```

---

## Aggiornamento

Per aggiornare SiteForge a una nuova versione:

```bash
cd /opt/siteforge
git pull
docker compose build
docker compose up -d
# Le migration vengono eseguite automaticamente all'avvio del container
```

---

## Comandi utili

```bash
docker compose ps                    # Stato dei container
docker compose logs -f               # Segui tutti i log
docker compose logs -f app           # Log solo del container PHP
docker compose logs -f queue         # Log del queue worker
docker compose exec app bash         # Shell nel container (come www-data)
sudo docker compose exec app bash    # Shell nel container (come root, se necessario)
make shell                           # Shortcut per shell nel container app
make logs                            # Shortcut per docker compose logs -f
make fresh                           # ⚠️ Reset DB e ri-migra (cancella tutti i dati)
```

---

## Risoluzione problemi

### Lo script fallisce al passo "Build immagini Docker"

Controlla la connessione internet e lo spazio su disco:
```bash
df -h /
docker system df
```

### Errore "network traefik_public not found"

La rete non è stata creata. Eseguila manualmente:
```bash
docker network create traefik_public
docker compose up -d
```

### Il pannello non risponde dopo l'installazione

Verifica che tutti i container siano attivi e controlla i log:
```bash
docker compose ps
docker compose logs app
docker compose logs nginx
docker compose logs traefik
```

### Errore permessi su `acme.json`

Traefik richiede esattamente il permesso `600` su questo file:
```bash
sudo chmod 600 docker/traefik/acme.json
docker compose restart traefik
```

### Errore permessi su `storage/`

```bash
sudo chmod -R 775 src/storage src/bootstrap/cache
sudo chown -R $USER:www-data src/storage src/bootstrap/cache
docker compose restart app queue scheduler
```

### L'utente non può usare Docker senza sudo

Il gruppo `docker` viene assegnato dallo script, ma serve un nuovo login:
```bash
# Verifica appartenenza al gruppo
groups $USER

# Se docker non appare, aggiungilo manualmente e rifai il login
sudo usermod -aG docker $USER
# Poi: logout e login, oppure:
newgrp docker
```

### Traefik Dashboard non accessibile

Il dashboard Traefik è esposto sulla porta `8080` ma **non viene aperto dal firewall** per sicurezza.  
Per accedervi temporaneamente dall'esterno:
```bash
sudo ufw allow 8080/tcp
# Accedi, poi richiudi la porta
sudo ufw delete allow 8080/tcp
```

---

## Note di sicurezza

- Le porte `3306` (MariaDB) e `6379` (Redis) **non sono esposte** sull'host: sono accessibili solo ai container nella rete interna Docker.
- La porta `8080` (Traefik dashboard) **non viene aperta** nel firewall dallo script.
- Le password del database vengono generate con `openssl rand -hex` e salvate solo in `src/.env`.
- Per abilitare HTTPS con Let's Encrypt, vedi la sezione dedicata in [architettura.md](architettura.md#https-configurazione-futura).
- Cambia la password admin dal pannello dopo il primo accesso.
