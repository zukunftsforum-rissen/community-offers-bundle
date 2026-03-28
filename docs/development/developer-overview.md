
# Zukunftwohnen – Zugangssystem: Technischer Überblick für Entwickler

Diese Datei dient als **Gedächtnisstütze für Entwickler**:
Wo liegt welche Logik, wie läuft der Door-Workflow,
und wie debuggt man typische Probleme.

Hinweis:
Dateipfade können je nach Bundle-Struktur leicht abweichen.
Die Klassennamen sind so dokumentiert,
dass sie per IDE-Suche schnell gefunden werden können.

---

# 1) Architektur in einem Satz

PWA (Mitglied)  
→ Contao/Symfony API  
→ OpenDoorService  
→ Door Jobs in Datenbank  
→ Device pollt regelmäßig  
→ Relais wird ausgelöst  
→ Confirm zurück an Server  

---

# Core Domain Rule (KRITISCH)

Das System unterstützt **zwei strikt getrennte Access-Workflows**:

1. Initial Access Request  
   → erstellt einen neuen Member

2. Additional Access Request  
   → erweitert Rechte eines bestehenden Members

Diese Workflows dürfen **niemals vermischt werden**.

Kritische Invariante:

Additional Requests dürfen **niemals** neue Member erzeugen.

Diese Regel ist eine **harte Domain-Regel** und darf durch Refactoring
oder Erweiterungen nicht verletzt werden.

---

Wichtige Prinzipien:

- Pull-Modell (keine eingehenden Verbindungen zum Device)
- Zustandsmaschine über `tl_co_door_job`
- confirmWindow konfigurierbar über:

    community_offers.confirm_window

- Poll erfolgt regelmäßig (typisch: ca. 2 Sekunden)

---

# 2) Code-Landkarte (wo finde ich was?)

## Controller (HTTP API)

---

## AccessController

Datei:

Controller/Api/AccessController.php

Zweck:

Frontend-Mitglied fordert Türöffnung an.

### Wichtige Methode

open(string $area, Request $request)

Typischer Ablauf:

- prüft Frontend-Login (Contao Session)
- validiert `area`
- prüft Zugriffsrechte
- ruft `OpenDoorService::openDoor()`
- OpenDoorService orchestriert DoorJobService
- liefert HTTP 202 bei Erfolg
- liefert HTTP 429 bei Rate-Limit

Antwort enthält typischerweise:

- jobId
- status
- expiresAt

---

## DeviceController

Datei:

Controller/Api/DeviceController.php

Zweck:

Device Poll & Confirm.

Polling erfolgt per:

POST /api/device/poll

(confirm ebenfalls POST)

Keine deviceId im URL-Pfad erforderlich,
da Authentifizierung über Token erfolgt.

---

### poll(Request $request)

Typischer Ablauf:

- authentifiziert Device
- liest erlaubte Areas
- ruft `dispatchJobs()`
- liefert Liste offener Jobs

---

### confirm(Request $request)

Body enthält:

- jobId
- nonce
- ok
- optional meta

Typische Antworten:

200 → erfolgreich bestätigt  
410 → expired  
403 → forbidden  
404 → jobId unbekannt  
409 → falscher Status  

Status:

expired ist ein **terminaler Zustand**.

Expired Jobs dürfen niemals wieder in:

- executed
- failed

übergehen.

---

# Access Request Workflows

Diese Workflows repräsentieren unterschiedliche Domain-Konzepte
und dürfen **nicht zusammengeführt werden**.

---

## Workflow A — Initial Access Request

Zweck:

Erstellen eines neuen Member-Accounts.

Erzeugt:

- tl_co_access_request
- tl_member

Statusfolge:

requested  
→ email_confirmed  
→ member_created  
→ password_set  
→ admin_approved  

---

## Workflow B — Additional Access Request

Zweck:

Zusätzliche Rechte für bestehenden Member vergeben.

Aktualisiert:

- member group assignments

Erzeugt **keinen neuen Member**.

Statusfolge:

requested  
→ email_confirmed  
→ admin_approved  
→ permissions_updated  

---

# 3) Services (Business Logic)

## OpenDoorService

Zentrale Orchestrierungsschicht zwischen:

- Controller
- DoorJobService
- Gateway

---

## DoorJobService

Zentrale Logik für:

- Job-Erstellung
- Dispatch
- Confirm
- Expiry

---

## AccessRequestService

Verarbeitet:

- Initial Requests
- Additional Requests

Implementiert:

- DOI Logik
- Workflow-Steuerung

---

# 4) Datenmodell

## tl_co_door_job

Zentrale Tabelle der Zustandsmaschine.

Status:

- pending
- dispatched
- executed
- failed
- expired

---

## tl_co_device

Speichert registrierte Geräte.

Wichtige Felder:

- deviceId
- areas
- apiTokenHash
- enabled
- isEmulator
- lastSeen

Diese Tabelle ist entscheidend für:

- Device Authentifizierung
- Device Monitoring
- Poll-Zuordnung

---

# 5) Troubleshooting

## Confirm kommt zu spät

Status:

expired

Prüfen:

- Poll-Intervall
- Netzwerk
- Device-Zeit (NTP)
- confirmWindow

---

## forbidden

Prüfen:

- deviceId
- nonce

---

## Job bleibt aktiv

Prüfen:

expireOldJobs()

---

# 6) Logging

Primäre Quelle:

tl_co_door_log

Loggt:

- memberId
- area
- jobId
- status transitions
- deviceId
- confirm outcome

---

# 7) Einstiegspunkte im Code

Wichtige Methoden:

AccessController::open()

OpenDoorService::openDoor()

DeviceController::poll()

DeviceController::confirm()

DoorJobService::*

AccessRequestService::*

---

# 8) Typische Weiterentwicklungen

- Monitoring
- Alarmierung
- Analyse ungewöhnlicher Fehlversuche
- Erweiterte Admin-Tools
