.PHONY: help install up down restart logs shell migrate seed build

# Default target
help:
	@echo "SiteForge - Comandi disponibili:"
	@echo ""
	@echo "  make install     - Prima installazione completa"
	@echo "  make up          - Avvia tutti i container"
	@echo "  make down        - Ferma tutti i container"
	@echo "  make restart     - Riavvia tutti i container"
	@echo "  make build       - Ricostruisce le immagini Docker"
	@echo "  make logs        - Mostra i log (tutti i servizi)"
	@echo "  make shell       - Shell PHP nel container app"
	@echo "  make migrate     - Esegui le migration"
	@echo "  make seed        - Esegui i seeder"
	@echo "  make artisan     - Esegui comando artisan (es: make artisan CMD='route:list')"
	@echo "  make composer    - Esegui comando composer (es: make composer CMD='require pkg')"
	@echo "  make fresh       - Reset DB e ri-migra"
	@echo "  make network     - Crea la rete Docker esterna"

install:
	@echo "==> Creazione rete Docker traefik_public..."
	@docker network create traefik_public 2>/dev/null || true
	@echo "==> Copia .env se non esiste..."
	@test -f src/.env || cp src/.env.example src/.env
	@echo "==> Creazione directory progetti..."
	@mkdir -p /opt/siteforge/projects /opt/siteforge/backups 2>/dev/null || \
		mkdir -p docker/data/projects docker/data/backups
	@echo "==> Creazione acme.json per Traefik..."
	@touch docker/traefik/acme.json && chmod 600 docker/traefik/acme.json
	@echo "==> Build immagini Docker..."
	@docker compose build
	@echo "==> Avvio servizi..."
	@docker compose up -d
	@echo ""
	@echo "==> Installazione completata!"
	@echo "==> Accedi a: http://siteforge.localhost"
	@echo "==> Traefik Dashboard: http://traefik.localhost:8080"

network:
	docker network create traefik_public 2>/dev/null || echo "Rete già esistente"

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build --no-cache

logs:
	docker compose logs -f

logs-app:
	docker compose logs -f app

logs-queue:
	docker compose logs -f queue

shell:
	docker compose exec app bash

shell-root:
	docker compose exec -u root app bash

migrate:
	docker compose exec app php artisan migrate --force

seed:
	docker compose exec app php artisan db:seed --force

fresh:
	docker compose exec app php artisan migrate:fresh --seed --force

artisan:
	docker compose exec app php artisan $(CMD)

composer:
	docker compose exec app composer $(CMD)

key:
	docker compose exec app php artisan key:generate --force

optimize:
	docker compose exec app php artisan optimize

optimize-clear:
	docker compose exec app php artisan optimize:clear

queue-status:
	docker compose exec app php artisan queue:monitor database:default

ps:
	docker compose ps

status: ps
