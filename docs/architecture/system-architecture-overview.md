# System Architecture Overview
Community Offers Bundle – Door Control System Overview

Dieses Dokument beschreibt die zentrale Systemarchitektur des Door-Control-Bereichs
im Community Offers Bundle.

Es zeigt die wichtigsten Bausteine und deren Zusammenspiel in den Modi:

- live
- emulation
- simulation

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
- Demo- und Präsentationsfähigkeit
- klare Trennung von realer Hardware und nicht-physischer Ausführung

---

# Hauptkomponenten

## App / Frontend

Die App ist der Einstiegspunkt für Nutzerinnen und Nutzer.

Aufgaben:

- Türöffnung anstoßen
- Responses anzeigen
- im Simulation-Modus direkte Demo-Rückmeldung anzeigen

---

## AccessController

HTTP-Einstieg für Türöffnungsanfragen.

Aufgaben:

- Request annehmen
- User-Kontext prüfen
- OpenDoorService aufrufen
- JSON-Response zurückgeben

---

## OpenDoorService

Fachlicher Einstieg für die Türöffnung.

Aufgaben:

- Berechtigung prüfen
- Mode berücksichtigen
- passendes Gateway wählen
- Logging und Audit anstoßen
- einheitliche Rückgabestruktur liefern

---

## DoorGatewayResolver

Wählt den technischen Ausführungspfad abhängig vom aktuellen Mode.

Zuordnung:

- live → RaspberryDoorGateway
- emulation → RaspberryDoorGateway / Workflow-Gateway
- simulation → SimulatorDoorGateway

---

## RaspberryDoorGateway

Technischer Einstieg für workflowbasierte Ausführung.

Aufgaben:

- DoorJobService verwenden
- DoorJob erzeugen
- Kontext an Workflow weitergeben

Der Name steht für den physischen bzw. workflowbasierten Ausführungspfad.
In `emulation` wird derselbe Workflowpfad verwendet, aber nur Emulator-Devices dürfen teilnehmen.

---

## SimulatorDoorGateway

Direkter Simulationspfad ohne Device-Workflow.

Aufgaben:

- sofortigen Erfolg liefern
- keinen Job erzeugen
- kein Polling / Confirm verwenden

---

## DoorJobService

Interne Workflow-Engine für workflowbasierte Modi.

Aufgaben:

- Jobs anlegen
- alte Jobs bereinigen
- Dispatch vorbereiten
- Confirm verarbeiten
- Status/Felder aktualisieren

Dieser Service ist die zentrale Engine für:

- live
- emulation

---

## Device API

Die Device API ist der Einstiegspunkt für Devices.

Typische Endpunkte:

- poll
- confirm
- heartbeat

Hier muss geprüft werden:

- aktueller Mode
- Device-Typ (`isEmulator`)

---

## Reale Devices

Reale Raspberry-Pi-Geräte.

Merkmal:

- `isEmulator = false`

Erlaubt in:

- live

Verboten in:

- emulation

---

## Emulator Devices

Nicht-physische Devices für Workflow-Tests.

Merkmal:

- `isEmulator = true`

Erlaubt in:

- emulation

Verboten in:

- live

---

## Logging / Audit / Workflow-Diagnose

Wichtige Querschnittskomponenten:

- LoggingService
- DoorAuditLogger
- DoorWorkflowTimelineService
- DoorWorkflowDiagramService

Diese Komponenten dienen der Nachvollziehbarkeit von:

- Öffnungsanfragen
- Workflow-Übergängen
- Fehlern
- Diagnosefällen

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
 │   Physical Device / Emulator Device
 │
 └─ SimulatorDoorGateway
     ↓
     Direkter Erfolg
```

---

# Modusbezogenes Verhalten

## live

Pfad:

App
→ AccessController
→ OpenDoorService
→ DoorGatewayResolver
→ RaspberryDoorGateway
→ DoorJobService
→ Device API
→ reales Device

Merkmale:

- Job wird erzeugt
- reale Hardware nimmt teil
- Emulatoren sind ausgeschlossen

---

## emulation

Pfad:

App
→ AccessController
→ OpenDoorService
→ DoorGatewayResolver
→ RaspberryDoorGateway
→ DoorJobService
→ Device API
→ Emulator Device

Merkmale:

- Job wird erzeugt
- voller Workflow
- keine reale Türöffnung
- reale Devices sind ausgeschlossen

---

## simulation

Pfad:

App
→ AccessController
→ OpenDoorService
→ DoorGatewayResolver
→ SimulatorDoorGateway

Merkmale:

- kein Job
- kein Polling
- kein Confirm
- direkter Erfolg

---

# Mode und Channel

Zur sauberen Trennung werden zwei Dinge gespeichert:

## mode

Fachlicher Betriebsmodus:

- live
- emulation
- simulation

## channel

Technischer Ausführungspfad:

- physical
- emulator
- simulator

Beispiele:

- live + physical
- emulation + emulator
- simulation + simulator

---

# Datenmodell

## Door Jobs

Door Jobs entstehen nur in:

- live
- emulation

Empfohlene Felder:

- mode
- channel

Simulation erzeugt keinen Job.

---

# Sicherheitsprinzip

Die wichtigste Schutzregel lautet:

- im Live-Modus dürfen keine Emulator-Devices teilnehmen
- im Emulation-Modus dürfen keine realen Devices teilnehmen
- im Simulation-Modus gibt es keine Device-Kommunikation

Diese Regeln müssen serverseitig in Poll/Confirm geprüft werden.

---

# Nutzen dieser Architektur

Die Architektur ermöglicht gleichzeitig:

- sicheren Echtbetrieb
- reproduzierbare Workflow-Tests
- Demo-/Präsentationsbetrieb
- klare Trennung von Verantwortlichkeiten
- einfache Analyse über Logs und Workflow-Daten
