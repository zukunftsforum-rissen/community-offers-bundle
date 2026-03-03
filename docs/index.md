# Community Offers Bundle – Dokumentation

Diese Doku beschreibt das **Zugangssystem (Door / Areas)** im Projekt *Zukunftwohnen* als Contao-Bundle inkl. API, Pull-Modell (Raspberry Pi), Security-Konzept, Datenmodell und Betriebsabläufen.

## Quick Links

- **Developer Overview:** `developer-overview.md`
- **API Door:** `api-door.md`
- **Security Model:** `security-model.md`
- **Data Model:** `data-model.md`
- **CI & Release:** `ci-and-release.md`
- **Ops Runbook:** `ops-runbook.md`

## System in einem Satz

Die App stellt **Open-Requests** an die API, die als **DoorJobs** gespeichert werden; ein **Raspberry Pi pollt** Jobs (Pull), prüft Bestätigung innerhalb eines **confirmWindow (30s)** und steuert anschließend die Türhardware.

## Diagramme

### Architektur (generated)

> Hinweis: Wenn das Bild fehlt, stelle sicher, dass `docs/diagrams/generated/architecture.svg` existiert (oder nutze die PDF/PNG Links darunter).

![Architekturübersicht](diagrams/generated/architecture.svg)

- PDF: `diagrams/generated/architecture.pdf`
- PNG: `diagrams/generated/architecture.png`

### Datenmodell (generated)

- PDF: `diagrams/generated/data-model.generated.pdf`
- PNG: `diagrams/generated/data-model.generated.png`
- PlantUML Source: `diagrams/generated/data-model.generated.puml`

## Wichtige Konzepte

- **Pull statt Push:** Der Pi baut keine eingehenden Verbindungen auf.
- **DoorJobService:** zentrale Business-Logik (Create/Confirm/Complete/Timeout).
- **confirmWindow:** Bestätigung muss innerhalb von 30 Sekunden passieren.
- **Audit & Observability:** Jobs/Statuswechsel sind nachvollziehbar.

## Einstieg für Maintainer

1. Lies `developer-overview.md`
2. Schau `api-door.md` + `api-examples.md`
3. Danach `security-model.md` und `ops-runbook.md`