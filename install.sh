#!/usr/bin/env bash
# =============================================================================
# SiteForge — Script di installazione automatica
# Testato su: Ubuntu 22.04 LTS, Ubuntu 24.04 LTS
# Uso: sudo bash install.sh
# =============================================================================

set -euo pipefail

# ── Colori output ─────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

# ── Helpers ───────────────────────────────────────────────────────────────────
info()    { echo -e "${BLUE}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; }
step()    { echo -e "\n${BOLD}${CYAN}▶ $*${RESET}"; }
die()     { error "$*"; exit 1; }

# ── Banner ────────────────────────────────────────────────────────────────────
echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════╗"
echo "  ║        SiteForge Installer           ║"
echo "  ║   Production Site Manager v1.0.0     ║"
echo "  ╚══════════════════════════════════════╝"
echo -e "${RESET}"

# ── Verifica prerequisiti script ─────────────────────────────────────────────
step "Verifica permessi"

if [[ $EUID -ne 0 ]]; then
    die "Questo script deve essere eseguito come root.\nUso: sudo bash install.sh"
fi
success "Esecuzione come root"

# Determina l'utente reale (chi ha usato sudo)
REAL_USER="${SUDO_USER:-$(whoami)}"
REAL_HOME=$(getent passwd "$REAL_USER" | cut -d: -f6)
info "Utente reale: ${REAL_USER} (home: ${REAL_HOME})"

# ── Verifica sistema operativo ────────────────────────────────────────────────
step "Verifica sistema operativo"

if [[ ! -f /etc/os-release ]]; then
    die "Sistema operativo non riconosciuto. Richiesto Ubuntu 22.04+."
fi

. /etc/os-release

if [[ "$ID" != "ubuntu" ]]; then
    die "Richiesto Ubuntu. Sistema rilevato: $ID $VERSION_ID"
fi

UBUNTU_VERSION="${VERSION_ID}"
info "Sistema: Ubuntu ${UBUNTU_VERSION}"

# Verifica versione minima Ubuntu 20.04
MAJOR_VERSION=$(echo "$UBUNTU_VERSION" | cut -d. -f1)
if [[ "$MAJOR_VERSION" -lt 20 ]]; then
    die "Richiesto Ubuntu 20.04 o superiore. Versione rilevata: ${UBUNTU_VERSION}"
fi

success "Sistema operativo compatibile"

# ── Directory del progetto ────────────────────────────────────────────────────
step "Verifica directory progetto"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
info "Directory progetto: ${SCRIPT_DIR}"

# Verifica che i file fondamentali esistano
for required in "docker-compose.yml" "src/.env.example" "docker/php/Dockerfile"; do
    if [[ ! -f "${SCRIPT_DIR}/${required}" ]]; then
        die "File mancante: ${required}\nAssicurati di eseguire install.sh dalla root del repository SiteForge."
    fi
done
success "Struttura progetto verificata"

# ── Aggiornamento pacchetti sistema ───────────────────────────────────────────
step "Aggiornamento pacchetti sistema (apt)"

info "Esecuzione apt-get update..."
apt-get update -qq

info "Upgrade pacchetti installati..."
apt-get upgrade -y -qq

info "Installazione pacchetti base..."
apt-get install -y -qq \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    wget \
    unzip \
    ufw \
    htop \
    net-tools \
    2>/dev/null

success "Pacchetti sistema aggiornati"

# ── Installazione Docker ──────────────────────────────────────────────────────
step "Installazione Docker"

if command -v docker &>/dev/null; then
    DOCKER_VERSION=$(docker --version | grep -oP '\d+\.\d+\.\d+' | head -1)
    info "Docker già installato: v${DOCKER_VERSION}"

    # Verifica versione minima Docker 24
    DOCKER_MAJOR=$(echo "$DOCKER_VERSION" | cut -d. -f1)
    if [[ "$DOCKER_MAJOR" -lt 24 ]]; then
        warn "Docker v${DOCKER_VERSION} potrebbe non essere compatibile. Versione consigliata: 24.0+"
        warn "Considera di aggiornare Docker prima di continuare."
    fi
else
    info "Docker non trovato. Installazione in corso..."

    # Rimozione versioni obsolete se presenti
    apt-get remove -y -qq docker docker-engine docker.io containerd runc 2>/dev/null || true

    # Aggiunta repository ufficiale Docker
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL "https://download.docker.com/linux/ubuntu/gpg" \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
        "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
        https://download.docker.com/linux/ubuntu \
        $(lsb_release -cs) stable" \
        | tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update -qq
    apt-get install -y -qq \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin

    systemctl enable docker
    systemctl start docker

    DOCKER_VERSION=$(docker --version | grep -oP '\d+\.\d+\.\d+' | head -1)
    success "Docker v${DOCKER_VERSION} installato"
fi

# Aggiunge l'utente reale al gruppo docker (per usare docker senza sudo)
if ! groups "$REAL_USER" | grep -q '\bdocker\b'; then
    usermod -aG docker "$REAL_USER"
    info "Utente '${REAL_USER}' aggiunto al gruppo docker"
    warn "Sarà necessario un nuovo login per usare Docker senza sudo"
fi

# ── Verifica Docker Compose v2 ────────────────────────────────────────────────
step "Verifica Docker Compose"

if docker compose version &>/dev/null; then
    COMPOSE_VERSION=$(docker compose version --short 2>/dev/null || echo "v2.x")
    success "Docker Compose v2 disponibile: ${COMPOSE_VERSION}"
else
    # Fallback: installa docker-compose-plugin manualmente
    info "Installazione docker-compose-plugin..."
    apt-get install -y -qq docker-compose-plugin
    success "Docker Compose v2 installato"
fi

# ── Rete Docker condivisa ─────────────────────────────────────────────────────
step "Configurazione rete Docker"

if docker network inspect traefik_public &>/dev/null; then
    info "Rete 'traefik_public' già esistente"
else
    docker network create traefik_public
    success "Rete Docker 'traefik_public' creata"
fi

# ── Directory dati ────────────────────────────────────────────────────────────
step "Creazione directory dati"

PROJECTS_PATH="/opt/siteforge/projects"
BACKUPS_PATH="/opt/siteforge/backups"
TEMPLATES_PATH="/opt/siteforge/templates"

for dir in "$PROJECTS_PATH" "$BACKUPS_PATH" "$TEMPLATES_PATH"; do
    if [[ ! -d "$dir" ]]; then
        mkdir -p "$dir"
        chown "$REAL_USER:$REAL_USER" "$dir" 2>/dev/null || true
        success "Directory creata: ${dir}"
    else
        info "Directory già esistente: ${dir}"
    fi
done

# Copia template built-in nella directory montata
if [[ -d "${SCRIPT_DIR}/templates" ]]; then
    cp -rn "${SCRIPT_DIR}/templates/." "$TEMPLATES_PATH/" 2>/dev/null || true
    info "Template built-in copiati in ${TEMPLATES_PATH}"
fi

# ── Configurazione .env ───────────────────────────────────────────────────────
step "Configurazione ambiente (.env)"

ENV_FILE="${SCRIPT_DIR}/src/.env"
ENV_EXAMPLE="${SCRIPT_DIR}/src/.env.example"

if [[ -f "$ENV_FILE" ]]; then
    warn "File src/.env già esistente. Salto configurazione automatica."
    warn "Modifica manualmente src/.env se necessario."
else
    cp "$ENV_EXAMPLE" "$ENV_FILE"
    success "File src/.env creato da .env.example"

    # ── Richiede configurazione interattiva ───────────────────────────────────
    echo ""
    echo -e "${BOLD}Configurazione interattiva${RESET}"
    echo "Premi INVIO per mantenere il valore predefinito mostrato tra [parentesi]."
    echo ""

    # Dominio
    read -rp "Dominio del pannello SiteForge [siteforge.localhost]: " INPUT_DOMAIN
    APP_DOMAIN="${INPUT_DOMAIN:-siteforge.localhost}"
    APP_URL="http://${APP_DOMAIN}"

    # Email admin
    read -rp "Email amministratore [admin@siteforge.local]: " INPUT_EMAIL
    ADMIN_EMAIL="${INPUT_EMAIL:-admin@siteforge.local}"

    # Password admin
    while true; do
        read -rsp "Password amministratore (min. 8 caratteri): " INPUT_PASSWORD
        echo ""
        if [[ ${#INPUT_PASSWORD} -ge 8 ]]; then
            ADMIN_PASSWORD="$INPUT_PASSWORD"
            break
        else
            warn "La password deve essere di almeno 8 caratteri."
        fi
    done

    # Password database (generata automaticamente)
    DB_PASSWORD=$(openssl rand -hex 16)
    DB_ROOT_PASSWORD=$(openssl rand -hex 20)

    # Aggiorna il .env
    sed -i "s|APP_URL=.*|APP_URL=${APP_URL}|g"                   "$ENV_FILE"
    sed -i "s|APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN}|g"           "$ENV_FILE"
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|g"         "$ENV_FILE"
    sed -i "s|DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}|g" "$ENV_FILE"
    sed -i "s|ADMIN_EMAIL=.*|ADMIN_EMAIL=${ADMIN_EMAIL}|g"         "$ENV_FILE"
    sed -i "s|ADMIN_PASSWORD=.*|ADMIN_PASSWORD=${ADMIN_PASSWORD}|g" "$ENV_FILE"
    sed -i "s|PROJECTS_BASE_PATH=.*|PROJECTS_BASE_PATH=${PROJECTS_PATH}|g" "$ENV_FILE"
    sed -i "s|TEMPLATES_BASE_PATH=.*|TEMPLATES_BASE_PATH=${TEMPLATES_PATH}|g" "$ENV_FILE"
    sed -i "s|BACKUP_PATH=.*|BACKUP_PATH=${BACKUPS_PATH}|g"        "$ENV_FILE"

    success "File src/.env configurato"
    info "Password DB generate automaticamente e salvate nel .env"
fi

# Aggiorna anche il .env principale (root del progetto) se necessario
ROOT_ENV="${SCRIPT_DIR}/.env"
if [[ ! -f "$ROOT_ENV" ]]; then
    cp "${SCRIPT_DIR}/.env.example" "$ROOT_ENV"
    # Sincronizza i valori dal src/.env
    DB_PASSWORD_VAL=$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)
    DB_ROOT_VAL=$(grep '^DB_ROOT_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)
    APP_DOMAIN_VAL=$(grep '^APP_DOMAIN=' "$ENV_FILE" | cut -d= -f2-)
    sed -i "s|APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN_VAL}|g"           "$ROOT_ENV"
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD_VAL}|g"         "$ROOT_ENV"
    sed -i "s|DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_VAL}|g"   "$ROOT_ENV"
    sed -i "s|PROJECTS_BASE_PATH=.*|PROJECTS_BASE_PATH=${PROJECTS_PATH}|g" "$ROOT_ENV"
fi

# ── File ACME per Traefik ─────────────────────────────────────────────────────
step "Configurazione Traefik"

ACME_FILE="${SCRIPT_DIR}/docker/traefik/acme.json"
if [[ ! -f "$ACME_FILE" ]]; then
    touch "$ACME_FILE"
    success "File acme.json creato"
fi
# Permessi obbligatori: Traefik rifiuta il file se non è 600
chmod 600 "$ACME_FILE"
success "Permessi acme.json impostati (600)"

# ── Permessi directory storage Laravel ───────────────────────────────────────
step "Permessi directory storage"

for dir in \
    "${SCRIPT_DIR}/src/storage" \
    "${SCRIPT_DIR}/src/bootstrap/cache"
do
    if [[ -d "$dir" ]]; then
        chmod -R 775 "$dir"
        # www-data è l'utente PHP-FPM nel container
        chown -R "$REAL_USER":www-data "$dir" 2>/dev/null || \
        chown -R "$REAL_USER":"$REAL_USER" "$dir"
    fi
done
success "Permessi storage impostati"

# ── Build immagini Docker ─────────────────────────────────────────────────────
step "Build immagini Docker"

info "Questo passaggio può richiedere alcuni minuti alla prima esecuzione..."
cd "$SCRIPT_DIR"
docker compose build --no-cache
success "Immagini Docker costruite"

# ── Avvio servizi ─────────────────────────────────────────────────────────────
step "Avvio servizi"

docker compose up -d
success "Container avviati"

# ── Attesa avvio database ─────────────────────────────────────────────────────
step "Attesa inizializzazione database"

info "Attendo che MariaDB sia pronto..."
MAX_WAIT=60
WAITED=0
until docker compose exec -T mariadb healthcheck.sh --connect --innodb_initialized &>/dev/null; do
    if [[ $WAITED -ge $MAX_WAIT ]]; then
        die "Timeout: MariaDB non è pronto dopo ${MAX_WAIT}s. Controlla: docker compose logs mariadb"
    fi
    echo -n "."
    sleep 2
    WAITED=$((WAITED + 2))
done
echo ""
success "MariaDB pronto dopo ${WAITED}s"

# ── Verifica container attivi ─────────────────────────────────────────────────
step "Verifica stato container"

FAILED_CONTAINERS=()
for service in traefik mariadb redis app nginx queue scheduler; do
    STATUS=$(docker compose ps --format "{{.State}}" "$service" 2>/dev/null || echo "missing")
    if [[ "$STATUS" == "running" ]]; then
        success "  ${service}: running"
    else
        warn "  ${service}: ${STATUS}"
        FAILED_CONTAINERS+=("$service")
    fi
done

if [[ ${#FAILED_CONTAINERS[@]} -gt 0 ]]; then
    warn "Alcuni container non sono in stato 'running': ${FAILED_CONTAINERS[*]}"
    warn "Controlla i log con: docker compose logs ${FAILED_CONTAINERS[0]}"
fi

# ── Firewall (UFW) ────────────────────────────────────────────────────────────
step "Configurazione firewall (UFW)"

if command -v ufw &>/dev/null; then
    UFW_STATUS=$(ufw status | head -1)
    if [[ "$UFW_STATUS" == *"inactive"* ]]; then
        info "UFW non attivo. Configurazione regole di base..."
        ufw allow ssh
        ufw allow 80/tcp
        ufw allow 443/tcp
        # Non esponiamo 8080 (Traefik dashboard) su internet
        ufw --force enable
        success "Firewall configurato: SSH, HTTP (80), HTTPS (443)"
    else
        info "UFW già attivo. Aggiunta regole HTTP/HTTPS..."
        ufw allow 80/tcp  &>/dev/null || true
        ufw allow 443/tcp &>/dev/null || true
        success "Regole HTTP/HTTPS aggiunte"
    fi
else
    warn "UFW non disponibile. Configura manualmente il firewall."
fi

# ── Riepilogo finale ──────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${GREEN}════════════════════════════════════════${RESET}"
echo -e "${BOLD}${GREEN}  Installazione completata con successo!${RESET}"
echo -e "${BOLD}${GREEN}════════════════════════════════════════${RESET}"
echo ""

# Legge i valori finali dal .env
FINAL_DOMAIN=$(grep '^APP_DOMAIN=' "${SCRIPT_DIR}/src/.env" | cut -d= -f2-)
FINAL_EMAIL=$(grep '^ADMIN_EMAIL=' "${SCRIPT_DIR}/src/.env" | cut -d= -f2-)

echo -e "  ${BOLD}Pannello di controllo:${RESET}"
echo -e "    URL:      ${CYAN}http://${FINAL_DOMAIN}/admin${RESET}"
echo -e "    Email:    ${CYAN}${FINAL_EMAIL}${RESET}"
echo -e "    Password: ${YELLOW}(quella impostata durante l'installazione)${RESET}"
echo ""
echo -e "  ${BOLD}Comandi utili:${RESET}"
echo -e "    ${CYAN}docker compose logs -f${RESET}          Segui i log in tempo reale"
echo -e "    ${CYAN}docker compose ps${RESET}               Stato dei container"
echo -e "    ${CYAN}make shell${RESET}                      Shell nel container app"
echo ""
echo -e "  ${BOLD}File importanti:${RESET}"
echo -e "    Configurazione:  ${SCRIPT_DIR}/src/.env"
echo -e "    Progetti:        /opt/siteforge/projects"
echo -e "    Backup:          /opt/siteforge/backups"
echo ""

if groups "$REAL_USER" | grep -q '\bdocker\b'; then
    info "Puoi usare 'docker' e 'make' senza sudo con l'utente '${REAL_USER}'"
else
    warn "Per usare Docker senza sudo, esci e rientra con l'utente '${REAL_USER}'"
fi

echo ""
echo -e "${BOLD}Documentazione completa: ${SCRIPT_DIR}/documentation/${RESET}"
echo ""
