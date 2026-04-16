#!/usr/bin/env bash
# =============================================================================
# SiteForge — Script di diagnosi e riparazione automatica
# Uso: sudo bash diagnose.sh
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

info()    { echo -e "${BLUE}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; }
fix()     { echo -e "${CYAN}[FIX]${RESET}   $*"; }
step()    { echo -e "\n${BOLD}${CYAN}▶ $*${RESET}"; }

FIXED=0
ERRORS=0

mark_fixed() { FIXED=$((FIXED + 1)); }
mark_error() { ERRORS=$((ERRORS + 1)); }

# ── Verifica root ─────────────────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    echo -e "${RED}Eseguire con sudo: sudo bash diagnose.sh${RESET}"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════╗"
echo "  ║     SiteForge Diagnosi & Repair      ║"
echo "  ╚══════════════════════════════════════╝"
echo -e "${RESET}"

# ── 1. Verifica file .env ─────────────────────────────────────────────────────
step "Verifica configurazione .env"

ROOT_ENV="${SCRIPT_DIR}/.env"
SRC_ENV="${SCRIPT_DIR}/src/.env"

if [[ ! -f "$SRC_ENV" ]]; then
    error "File src/.env mancante. Esegui prima install.sh"
    exit 1
fi
success "src/.env trovato"

# Leggi valori chiave
APP_DOMAIN=$(grep '^APP_DOMAIN=' "$SRC_ENV" | cut -d= -f2- || echo "")
APP_URL=$(grep '^APP_URL=' "$SRC_ENV" | cut -d= -f2- || echo "")
APP_KEY=$(grep '^APP_KEY=' "$SRC_ENV" | cut -d= -f2- || echo "")

if [[ -z "$APP_DOMAIN" ]]; then
    error "APP_DOMAIN non impostato in src/.env"
    mark_error
else
    success "APP_DOMAIN = ${APP_DOMAIN}"
fi

# Verifica APP_KEY
if [[ -z "$APP_KEY" || "$APP_KEY" == "base64:" ]]; then
    warn "APP_KEY non impostato — Laravel non funzionerà correttamente"
    fix "Generazione APP_KEY..."
    docker compose exec -T app php artisan key:generate --force 2>/dev/null && \
        { success "APP_KEY generato"; mark_fixed; } || \
        warn "Impossibile generare APP_KEY ora (container non pronto?)"
else
    success "APP_KEY impostato"
fi

# Verifica coerenza tra root .env e src/.env
# La fonte di verità è: root .env se ha un valore, altrimenti src/.env
ROOT_DOMAIN=$(grep '^APP_DOMAIN=' "$ROOT_ENV" 2>/dev/null | cut -d= -f2- || echo "")

if [[ -z "$APP_DOMAIN" && -n "$ROOT_DOMAIN" ]]; then
    # src/.env non ha APP_DOMAIN ma root .env sì → aggiunge a src/.env
    warn "APP_DOMAIN mancante in src/.env — aggiungo da root .env ('${ROOT_DOMAIN}')"
    echo "APP_DOMAIN=${ROOT_DOMAIN}" >> "$SRC_ENV"
    APP_DOMAIN="$ROOT_DOMAIN"
    APP_URL="http://${APP_DOMAIN}"
    success "APP_DOMAIN aggiunto a src/.env"
    mark_fixed
elif [[ -n "$APP_DOMAIN" && "$ROOT_DOMAIN" != "$APP_DOMAIN" ]]; then
    # src/.env ha un valore diverso → aggiorna root .env
    warn "APP_DOMAIN non coerente: root .env='${ROOT_DOMAIN}' src/.env='${APP_DOMAIN}'"
    fix "Sincronizzo root .env con src/.env..."
    sed -i "s|APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN}|g" "$ROOT_ENV"
    sed -i "s|APP_URL=.*|APP_URL=${APP_URL}|g"           "$ROOT_ENV"
    success "root .env sincronizzato"
    mark_fixed
elif [[ -z "$APP_DOMAIN" && -z "$ROOT_DOMAIN" ]]; then
    error "APP_DOMAIN non impostato né in src/.env né in root .env"
    info "Imposta APP_DOMAIN manualmente in src/.env, poi ri-esegui diagnose.sh"
    mark_error
else
    success "root .env coerente con src/.env (${APP_DOMAIN})"
fi

if [[ ! -f "$ROOT_ENV" ]]; then
    warn "root .env mancante — creazione da src/.env..."
    cp "${SCRIPT_DIR}/.env.example" "$ROOT_ENV"
    sed -i "s|APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN}|g"     "$ROOT_ENV"
    sed -i "s|APP_URL=.*|APP_URL=${APP_URL}|g"               "$ROOT_ENV"
    DB_PASS=$(grep '^DB_PASSWORD=' "$SRC_ENV" | cut -d= -f2-)
    DB_ROOT=$(grep '^DB_ROOT_PASSWORD=' "$SRC_ENV" | cut -d= -f2-)
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|g"       "$ROOT_ENV"
    sed -i "s|DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT}|g" "$ROOT_ENV"
    success "root .env creato"
    mark_fixed
fi

# ── 2. Verifica container ─────────────────────────────────────────────────────
step "Verifica stato container"

SERVICES=(traefik mariadb redis app nginx queue scheduler)
NEED_RESTART=()

for svc in "${SERVICES[@]}"; do
    STATUS=$(docker compose ps --format "{{.State}}" "$svc" 2>/dev/null || echo "missing")
    case "$STATUS" in
        running)  success "  ${svc}: running" ;;
        healthy)  success "  ${svc}: healthy" ;;
        missing)
            warn "  ${svc}: non trovato"
            NEED_RESTART+=("$svc")
            mark_error
            ;;
        *)
            warn "  ${svc}: ${STATUS}"
            NEED_RESTART+=("$svc")
            mark_error
            ;;
    esac
done

if [[ ${#NEED_RESTART[@]} -gt 0 ]]; then
    fix "Avvio container non attivi: ${NEED_RESTART[*]}"
    docker compose up -d "${NEED_RESTART[@]}"
    mark_fixed
fi

# ── 3. Verifica compatibilità Docker API / Traefik ────────────────────────────
step "Verifica compatibilità Traefik ↔ Docker"

DOCKER_MAJOR=$(docker version --format '{{.Server.Version}}' 2>/dev/null | cut -d. -f1 || echo "0")

# Controlla se Traefik ha errori Docker API nei log
TRAEFIK_API_ERROR=$(docker compose logs traefik --tail=50 2>/dev/null \
    | grep -c "client version.*too old" || true)

if [[ "$TRAEFIK_API_ERROR" -gt 0 ]]; then
    warn "Traefik non riesce a connettersi al Docker daemon (API version mismatch)"
    info "Docker ${DOCKER_MAJOR}.x richiede API >= 1.40, Traefik usa 1.24"
    fix "Passaggio al file provider (routing statico, indipendente dalla versione Docker)..."

    # 3a. Rimuovi Docker provider da traefik.yml
    TRAEFIK_YML="${SCRIPT_DIR}/docker/traefik/traefik.yml"
    if grep -q 'endpoint: "unix:///var/run/docker.sock"' "$TRAEFIK_YML"; then
        # Rimuovi il blocco docker provider preservando il resto
        python3 - "$TRAEFIK_YML" << 'PYEOF'
import sys, re

with open(sys.argv[1]) as f:
    content = f.read()

# Rimuovi il blocco docker sotto providers:
content = re.sub(
    r'(\bproviders:\s*\n)'           # riga "providers:"
    r'((?:[ \t]+.*\n)*?)'            # righe di altri provider prima di docker (es. file)
    r'[ \t]+docker:\s*\n'            # riga "  docker:"
    r'(?:[ \t]+.*\n)*',              # righe figlie del blocco docker
    lambda m: m.group(1) + m.group(2),
    content
)

with open(sys.argv[1], 'w') as f:
    f.write(content)
print("traefik.yml aggiornato")
PYEOF
        success "Docker provider rimosso da traefik.yml"
    else
        info "Docker provider già assente da traefik.yml"
    fi

    # 3b. Rimuovi il mount del socket Docker da Traefik in docker-compose.yml
    COMPOSE="${SCRIPT_DIR}/docker-compose.yml"
    if grep -q 'var/run/docker.sock.*ro' "$COMPOSE"; then
        sed -i '/- \/var\/run\/docker.sock:\/var\/run\/docker.sock:ro/d' "$COMPOSE"
        success "Mount socket Docker rimosso da Traefik"
    fi

    # 3c. Crea/aggiorna il file di routing statico
    DYNAMIC_DIR="${SCRIPT_DIR}/docker/traefik/dynamic"
    mkdir -p "$DYNAMIC_DIR"
    cat > "${DYNAMIC_DIR}/siteforge.yml" << 'EOF'
http:
  routers:
    siteforge:
      rule: "Host(`{{ env \"APP_DOMAIN\" }}`)"
      entrypoints:
        - web
      service: siteforge-nginx

  services:
    siteforge-nginx:
      loadBalancer:
        servers:
          - url: "http://nginx:80"
EOF
    success "File di routing statico creato: docker/traefik/dynamic/siteforge.yml"

    # 3d. Assicurati che APP_DOMAIN sia passato a Traefik
    if ! grep -A5 'container_name: siteforge_traefik' "$COMPOSE" | grep -q 'APP_DOMAIN'; then
        python3 - "$COMPOSE" "$APP_DOMAIN" << 'PYEOF'
import sys, re

compose_file = sys.argv[1]
domain = sys.argv[2]

with open(compose_file) as f:
    content = f.read()

# Inserisci environment block dopo restart: unless-stopped nel blocco traefik
env_block = "    environment:\n      - APP_DOMAIN=" + domain + "\n"
content = re.sub(
    r'(container_name: siteforge_traefik\n    restart: unless-stopped\n)',
    r'\1' + env_block,
    content
)

with open(compose_file, 'w') as f:
    f.write(content)
print("APP_DOMAIN aggiunto a Traefik in docker-compose.yml")
PYEOF
    fi

    # 3e. Ricrea Traefik
    fix "Riavvio Traefik con nuova configurazione..."
    docker compose up -d --force-recreate traefik
    sleep 3
    mark_fixed

    # Verifica che gli errori siano spariti
    NEW_ERRORS=$(docker compose logs traefik --tail=20 2>/dev/null \
        | grep -c "client version.*too old" || true)
    if [[ "$NEW_ERRORS" -eq 0 ]]; then
        success "Traefik ora usa il file provider — nessun errore Docker API"
    else
        warn "Traefik ancora in errore — controlla manualmente: docker compose logs traefik"
    fi
else
    success "Traefik compatibile con Docker (nessun errore API)"
fi

# ── 4. Verifica redirect HTTPS senza certificato ──────────────────────────────
step "Verifica configurazione HTTPS"

TRAEFIK_YML="${SCRIPT_DIR}/docker/traefik/traefik.yml"
if grep -q 'to: websecure' "$TRAEFIK_YML"; then
    CERT_RESOLVER=$(grep -c 'certResolver:' "$TRAEFIK_YML" || true)
    RESOLVER_ENABLED=$(grep -c '^\s*certificatesResolvers:' "$TRAEFIK_YML" || true)

    if [[ "$RESOLVER_ENABLED" -eq 0 ]]; then
        warn "Redirect HTTP→HTTPS attivo ma nessun certificato configurato"
        fix "Disabilito il redirect HTTPS..."
        python3 - "$TRAEFIK_YML" << 'PYEOF'
import sys, re

with open(sys.argv[1]) as f:
    content = f.read()

# Rimuovi il blocco redirections sotto web entrypoint
content = re.sub(
    r'(\s+web:\s*\n\s+address: ":80"\s*\n)'
    r'\s+http:\s*\n\s+redirections:\s*\n(?:\s+.*\n)*?(?=\s+websecure:|\Z)',
    r'\1',
    content
)

with open(sys.argv[1], 'w') as f:
    f.write(content)
print("Redirect HTTPS rimosso")
PYEOF
        docker compose restart traefik
        success "Redirect HTTPS disabilitato"
        mark_fixed
    else
        success "Redirect HTTPS attivo con certificato configurato"
    fi
else
    success "Nessun redirect HTTPS forzato"
fi

# ── 5. Verifica raggiungibilità HTTP ──────────────────────────────────────────
step "Verifica raggiungibilità HTTP"

sleep 2  # Lascia stabilizzare Traefik

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    --max-time 5 \
    -H "Host: ${APP_DOMAIN}" \
    "http://localhost/admin" 2>/dev/null || echo "000")

case "$HTTP_CODE" in
    200|302|301)
        success "HTTP risponde con ${HTTP_CODE} — pannello raggiungibile su http://${APP_DOMAIN}/admin"
        ;;
    404)
        warn "HTTP 404 — Traefik non trova la route per '${APP_DOMAIN}'"
        info "Verifica: docker compose logs traefik --tail=20"
        mark_error
        ;;
    000)
        warn "Nessuna risposta HTTP — porta 80 non raggiungibile o container non pronti"
        mark_error
        ;;
    *)
        warn "HTTP risponde con codice inatteso: ${HTTP_CODE}"
        mark_error
        ;;
esac

# ── 6. Verifica migrazioni database ──────────────────────────────────────────
step "Verifica database e migrazioni"

APP_STATUS=$(docker compose ps --format "{{.State}}" app 2>/dev/null || echo "missing")

if [[ "$APP_STATUS" == "running" ]]; then
    MIGRATE_STATUS=$(docker compose exec -T app php artisan migrate:status \
        --no-interaction 2>/dev/null | tail -5 || echo "errore")

    if echo "$MIGRATE_STATUS" | grep -q "No"; then
        warn "Alcune migration non eseguite"
        fix "Esecuzione migrazioni..."
        docker compose exec -T app php artisan migrate --force --no-interaction && \
            { success "Migrazioni completate"; mark_fixed; } || \
            { error "Migrazioni fallite — controlla: docker compose logs app"; mark_error; }
    elif echo "$MIGRATE_STATUS" | grep -q "errore\|Error\|error"; then
        warn "Impossibile verificare lo stato delle migrazioni"
        info "Controlla: docker compose exec app php artisan migrate:status"
        mark_error
    else
        success "Migrazioni aggiornate"
    fi
else
    warn "Container app non attivo, skip verifica migrazioni"
fi

# ── Riepilogo ─────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}════════════════════════════════════════${RESET}"
if [[ $ERRORS -eq 0 && $FIXED -eq 0 ]]; then
    echo -e "${GREEN}${BOLD}  Tutto OK — nessun problema rilevato${RESET}"
elif [[ $ERRORS -eq 0 ]]; then
    echo -e "${GREEN}${BOLD}  ${FIXED} problema/i risolto/i automaticamente${RESET}"
else
    echo -e "${YELLOW}${BOLD}  ${FIXED} risolto/i  |  ${ERRORS} da verificare manualmente${RESET}"
fi
echo -e "${BOLD}════════════════════════════════════════${RESET}"
echo ""
echo -e "  Pannello: ${CYAN}http://${APP_DOMAIN}/admin${RESET}"
echo -e "  Log app:  ${CYAN}docker compose logs app --tail=50${RESET}"
echo -e "  Log all:  ${CYAN}docker compose logs -f${RESET}"
echo ""
