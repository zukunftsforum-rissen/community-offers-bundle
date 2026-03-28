# System Architecture Overview
Community Offers Bundle – Door Control System Overview

Dieses Dokument beschreibt die zentrale Systemarchitektur des Door-Control-Bereichs
im Community Offers Bundle.

Es zeigt die wichtigsten Bausteile und deren Zusammenspiel in den Modi:

- live
- emulator

---

# Ziel

Die Architektur trennt klar zwischen:

- App-/Frontend-Einstieg
- fachlicher Orchestrierung
- Workflow-/Job-Logik
- Device-Kommunikation
- Logging / Audit / Diagnose

Dadurch werden folgende Ziele erreicht:

- sicherer Produktivbetrieb
- nachvollziehbare Fehleranalyse
- reproduzierbare Workflow-Tests
- klare Trennung von realer Hardware und Emulator-Ausführung

---

# Hauptkomponenten

## App / Frontend

Die App ist der Einstiegspunkt für Nutzerinnen und Nutzer.

Aufgaben:

- Türöffnung anstoßen
- Status anzeigen
- Workflow-Ergebnisse darstellen

---

## AccessController

HTTP-Einstieg für Türöffnungsanfragen.

Aufgaben:

- Request annehmen
- Benutzer authentifizieren
- OpenDoorService aufrufen
- JSON-Response zurückgeben

---

## OpenDoorService

Fachlicher Einstiegspunkt für die Türöffnung.

Aufgaben:

- Zugriffsrechte prüfen
- aktuellen Mode berücksichtigen
- passendes Gateway auswählen
- Logging und Audit auslösen
- standardisierte Antwort erzeugen

Dieser Service enthält **keine Hardware-Logik**.

---

## DoorGatewayResolver

Wählt das passende Gateway basierend auf dem aktuellen Mode.

Zuordnung:

live → RaspberryDoorGateway
emulator → EmulatorDoorGateway

Genau **ein Gateway muss den Mode unterstützen**.

Mehrere passende Gateways führen zu einer Exception.

---

## RaspberryDoorGateway

Gateway für reale Hardware-Ausführung.

Verwendung:

mode = live
channel = physical

Aufgaben:

- DoorJob über DoorJobService erzeugen
- Workflow starten
- Jobdaten bereitstellen

Die reale Türöffnung erfolgt indirekt
über das Device (z. B. Raspberry Pi).

---

## EmulatorDoorGateway

Gateway für Emulator-Ausführung.

Verwendung:

mode = emulator
channel = emulator

Aufgaben:

- DoorJob erzeugen
- vollständigen Workflow simulieren
- keine reale Hardware auslösen

Der Emulator arbeitet über denselben Workflow,
aber ohne physische Türöffnung.

---

## DoorJobService

Zentrale Workflow-Engine.

Aufgaben:

- DoorJobs erzeugen
- Dispatch vorbereiten
- Confirm verarbeiten
- Status aktualisieren
- Expiry behandeln

Dieser Service ist verantwortlich für:

- Job Lifecycle
- Workflow-Zustände
- Zeitfenster (confirm_window)

DoorJobs entstehen nur in:

live
emulator

---

## Device API

HTTP-Endpunkte für Devices.

Typische Endpunkte:

/api/device/poll
/api/device/confirm
/api/device/heartbeat

Hier werden geprüft:

- aktueller Mode
- Device-Typ (`isEmulator`)
- Berechtigung zur Teilnahme

---

## Reale Devices

Physische Geräte (z. B. Raspberry Pi).

Merkmal:

isEmulator = false

Erlaubt in:

live

Nicht erlaubt in:

emulator

---

## Emulator Devices

Virtuelle Geräte zur Workflow-Simulation.

Merkmal:

isEmulator = true

Erlaubt in:

emulator

Nicht erlaubt in:

live

---

## Logging / Audit / Workflow-Diagnose

Wichtige Querschnittskomponenten:

- LoggingService
- DoorAuditLogger
- DoorWorkflowTimelineService
- DoorWorkflowDiagramService

Diese Komponenten ermöglichen:

- vollständige Nachvollziehbarkeit
- Fehleranalyse
- Diagnose
- Workflow-Visualisierung

---

# Architekturdiagramm

## Vereinfachte Übersicht

```text
User
 ↓
App / Frontend
 ↓
AccessController
 ↓
OpenDoorService
 ↓
DoorGatewayResolver
 ├─ RaspberryDoorGateway
 │   ↓
 │   DoorJobService
 │   ↓
 │   Database (door jobs)
 │   ↓
 │   Device API
 │   ↓
 │   Physical Device
 │
 └─ EmulatorDoorGateway
     ↓
     DoorJobService
     ↓
     Database (door jobs)
     ↓
     Device API
     ↓
     Emulator Device
```

---

# Mode und Channel

Zur sauberen Trennung werden zwei Eigenschaften gespeichert.

## mode

Fachlicher Betriebsmodus:

live
emulator

---

## channel

Technischer Ausführungspfad:

physical
emulator

Beispiele:

live + physical
emulator + emulator

---

# Datenmodell

## Door Jobs

Door Jobs entstehen nur in:

live
emulator

Empfohlene Felder:

mode
channel
area
correlationId
expiresAt

Diese Felder ermöglichen:

- vollständige Nachverfolgung
- Debugging
- Diagnose

---

# Sicherheitsprinzip

Die wichtigste Schutzregel lautet:

Live-Modus:
    Emulator-Devices verboten

Emulator-Modus:
    reale Devices verboten

Diese Regeln müssen serverseitig geprüft werden
(z. B. im DeviceAuthService oder DeviceAccessPolicy).

---

# Nutzen dieser Architektur

Die Architektur ermöglicht gleichzeitig:

- sicheren Echtbetrieb
- reproduzierbare Workflow-Tests
- klare Trennung von Hardware und Emulator
- vollständige Auditierbarkeit
- einfache Diagnose
