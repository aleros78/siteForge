# SiteForge — Documentazione

## Indice

| Documento | Contenuto |
|-----------|-----------|
| [Installazione](installazione.md) | Requisiti, `install.sh` automatico, setup manuale, risoluzione problemi |
| [Architettura](architettura.md) | Stack tecnologico, struttura, flussi dati |
| [Moduli](moduli.md) | Documentazione di ciascun modulo del sistema |
| [Template Engine](template-engine.md) | Come funzionano i template, placeholder, creazione template custom |
| [Sviluppo](sviluppo.md) | Guida per estendere il sistema, aggiungere moduli e contribuire |

---

## Panoramica rapida

SiteForge è un **gestore self-hosted di siti e applicazioni in produzione** basato su Docker.  
Permette a sviluppatori, agenzie e software house di creare, deployare e gestire più progetti da un unico pannello.

```
Utente → Pannello Filament → Job Queue → Docker Compose → Progetto live
```

### Caratteristiche principali

- Creazione progetti da template riutilizzabili
- Generazione automatica di docker-compose, Nginx e .env
- Lifecycle container: start / stop / restart / redeploy
- Gestione domini e routing via Traefik
- Backup database automatici
- Health check periodici
- Audit log completo di ogni operazione
- Clone/staging progetti
- Architettura predisposta per licensing commerciale
