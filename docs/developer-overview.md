# Zukunftwohnen – Zugangssystem: Technischer Überblick für Entwickler

Diese Datei ist als **Gedächtnisstütze** gedacht: Wo ist was im Code, wie läuft der Job-Flow, wie debuggt man, und wie sieht das Datenmodell aus.

> Hinweis: Dateipfade können je nach Bundle-Struktur leicht abweichen. Die Klassennamen/Methoden sind so dokumentiert, dass du sie per IDE-Suche schnell findest.

---

## 1) Architektur in einem Satz

**PWA (Mitglied) → Contao/Symfony API (Door Jobs in DB) → Raspberry Pi (Pull/Poll) → Relais → Confirm zurück**

- Keine eingehenden Verbindungen am Pi (Pull-Modell).
- Zustandsmaschine über DB-Tabelle `tl_co_door_job`.
- `confirmWindow = 30s` (dispatched TTL über `dispatchedAt`).

---

## 2) Code-Landkarte (wo finde ich was?)

### Controller (HTTP API)

#### `AccessController`
**Datei:** `.../Controller/Api/AccessController.php`  
**Zweck:** Frontend-Mitglied fordert Türöffnung an.

**Wichtige Methoden**
- `open(string $area, Request $request): JsonResponse`
  - prüft FE-Login (Session/Cookie)
  - validiert `area` (Whitelist)
  - (optional) Rechtecheck über Access/Policy Service
  - ruft `DoorJobService->expireOldJobs()` (best effort)
  - ruft `DoorJobService->createOpenJob(...)`
  - gibt **202** mit `jobId/status/expiresAt` oder **429** mit `retryAfterSeconds` zurück

- (optional / aus Backup übernommen)
  - `whoami()` – Debug/Info: aktueller Member, Areas, Auth-Status
  - `request()` – DOI/Access-Request Flow (siehe `AccessRequestService`)

**Typische Fehlerbilder**
- 401 bei CLI/curl → fehlende Contao-Session-Cookies (in der PWA ok).
- 404 invalid_area → falscher slug / nicht in Whitelist.

---

#### `DeviceController`
**Datei:** `.../Controller/Api/DeviceController.php`  
**Zweck:** Raspberry Pi Poll & Confirm (Pull-Modell).

**Wichtige Methoden**
- `poll(string $deviceId, Request $request): JsonResponse`
  - authentifiziert Device (deviceId + Secret/Hash)
  - liest `areas` (CSV)
  - ruft `DoorJobService->dispatchJobs($deviceId, $areas, $limit)`
  - Antwort: `{ jobs: [{jobId, area, nonce, expiresInMs}], nextPollInMs }`

- `confirm(string $deviceId, Request $request): JsonResponse`
  - Body: `jobId`, `nonce`, `ok`, optional `meta`
  - ruft `DoorJobService->confirmJobDetailed(...)` (oder äquivalent)
  - **Empfohlener Contract**
    - 200: bestätigt oder idempotent final (nonce+device passt)
    - 410: confirm_timeout (zu spät, Job expired)
    - 403: forbidden (device/nonce mismatch)
    - 404: not_found (jobId unbekannt)
    - 409: not_dispatchable (Job nicht in `dispatched`)

**Typische Fehlerbilder**
- confirm kommt als 200/accepted=false → sollte künftig 410/409/… sein (Contract klar halten).
- poll liefert job, confirm zu spät → DB `expired / TIMEOUT / Confirm timeout`.

---

### Services (Business Logic)

#### `DoorJobService`
**Datei:** `.../Service/DoorJobService.php`  
**Zweck:** Zentrale Business-Logik für Door-Jobs: anlegen, dispatchen, confirm, timeouts.

**Wichtige Konstanten**
- `CONFIRM_WINDOW_SECONDS = 30`

**Wichtige Methoden**
- `createOpenJob(int $memberId, string $area, string $ip = '', string $userAgent = ''): array`
  - **muss** am Anfang: `expireOldJobs()` (Housekeeping, Idempotenz)
  - Rate Limit (Member+Area, z.B. 3/min)
  - Locks (Member+Area + Area global)
  - Idempotenz: existierenden aktiven Job wiederverwenden
    - `pending` ist aktiv solange `expiresAt >= now`
    - `dispatched` ist aktiv solange `dispatchedAt >= now-30`
  - legt neuen `pending` Job an (setzt `expiresAt`)
  - Rückgabe u.a.: `httpStatus`, `jobId`, `status`, `expiresAt`, `retryAfterSeconds`

- `dispatchJobs(string $deviceId, array $areas, int $limit = 3): array`
  - (empfohlen) `expireOldJobs()` am Anfang
  - claimed Jobs: `pending` → `dispatched`
  - setzt: `dispatchToDeviceId`, `dispatchedAt`, `nonce`, `attempts++`
  - liefert Liste für poll: `{id, area, nonce, expiresAt}` (expiresAt nur pending-bezogen, UI nutzt `expiresInMs`)

- `confirmJob(...)` / `confirmJobDetailed(...)`
  - validiert: status, deviceId, nonce
  - timeout check: `dispatchedAt < now-30` → expired + confirm_timeout
  - setzt final: `executed` oder `failed` und `executedAt`, `resultCode`, `resultMessage`
  - idempotent: wenn bereits final und device+nonce passt → ok

- `expireOldJobs(): void`
  - `pending` mit `expiresAt < now` → `expired` (pending timeout)
  - `dispatched` mit `dispatchedAt < now-30` → `expired` (confirm timeout)

---

#### `AccessRequestService` (DOI/Access-Requests)
**Datei:** `.../Service/AccessRequestService.php`  
**Zweck:** Double-Opt-In / Zugangsanfrage-Flows (z.B. Area-Zugriff anfordern).

**Wichtige Methoden**
- `sendOrResendDoiForArea(...)`
  - verschickt DOI-Mail oder resend (abhängig von Status)
- (weitere Methoden je nach Implementierung: verify token, activate rights, etc.)

**Typische Fehlerbilder**
- Controller ruft falsche Methode (z.B. `requestAccess`) → existiert nicht.
  - Richtiger Name war: `sendOrResendDoiForArea(...)`.

---

## 3) Datenmodell

### Tabelle `tl_co_door_job` (Kern der Zustandsmaschine)

**Empfohlene/typische Felder**
- Identität/Meta
  - `id` (PK)
  - `tstamp`
  - `createdAt`
  - `area` (string)
  - `status` (`pending|dispatched|executed|failed|expired`)

- Request-Quelle (PWA)
  - `requestedByMemberId` (int)
  - `requestIp` (string)
  - `userAgent` (string)

- Pending TTL
  - `expiresAt` (int unix) **nur relevant für `pending`**
    - bei `dispatched` kann `0`/leer sein

- Dispatch (Pi)
  - `dispatchToDeviceId` (string)
  - `dispatchedAt` (int unix)
  - `nonce` (string, zufällig, hex)
  - `attempts` (int)

- Ergebnis (Confirm)
  - `executedAt` (int unix)
  - `resultCode` (`OK|ERR|TIMEOUT|...`)
  - `resultMessage` (kurzer Text, optional JSON-suffix gekürzt)

**Timeout-Regeln**
- pending: `expiresAt < now` → expired + `Pending timeout`
- dispatched: `dispatchedAt < now-30` → expired + `Confirm timeout`

---

## 4) Contao Backend-Module (Admin/Redaktion)

Je nach Bundle-Konfiguration liegen diese meist unter:
- `.../Resources/contao/dca/*.php` (DCA Definition)
- `.../Resources/contao/languages/*/*.php` (Labels/Übersetzungen)
- `.../Resources/contao/templates/*` (BE/FE Templates)
- `.../ContaoManager/Plugin.php` oder `.../DependencyInjection/*` (Registrierung)

**Typische Backend-Bereiche in diesem Projekt**
1. **Areas/Türen** (Konfiguration)
   - Slugs: `depot`, `swap-house`, `workshop`, `sharing`
   - ggf. Zuordnung device ↔ areas

2. **Door Jobs / Protokoll**
   - Liste der Jobs (Filter: area, status, Zeitraum, Member)
   - Detailansicht: `dispatchToDeviceId`, `resultMessage`, Zeiten
   - nützlich für Fehlersuche (timeout, forbidden, rate limit)

3. **Access Requests / DOI**
   - offene DOI-Anfragen
   - resend DOI
   - (optional) Freischalten/Berechtigungen

> Wenn du mir eure DCA-Dateien/Tabellen schickst, ergänze ich hier die exakten Table-Namen, List-Operationen und Felder 1:1.

---

## 5) Troubleshooting / Fehlersuche (Quick Playbook)

### A) „PWA ok, curl 401“
- Ursache: curl hat keine Contao-Frontend-Session-Cookies.
- Lösung: über Browser-Request Cookies übernehmen oder in der PWA testen.

### B) Job wird gepollt, confirm kommt zu spät
- Symptom: DB `expired`, `TIMEOUT`, `Confirm timeout`
- Prüfen:
  - confirmDelay (e2e) > 30s?
  - Pi-Uhrzeit (NTP) korrekt?
  - Poll-Intervall zu groß?
- Lösung:
  - Poll häufiger / Confirm schneller / confirmWindow ggf. bewusst erhöhen (aber Sicherheitsabwägung).

### C) Doppelrequests / „Tür ist gerade in Benutzung“
- Ursache: Locks (Member+Area oder Area global)
- Prüfen:
  - Cache backend (PSR-6) funktioniert? TTL korrekt?
  - retryAfterSeconds im Response

### D) Device forbidden / nonce mismatch
- Ursache:
  - falsches deviceId/secret
  - nonce falsch oder bereits „verbraucht“
- Prüfen:
  - poll-response nonce
  - confirm-body exakt
  - DB: `dispatchToDeviceId`, `nonce`

### E) „Job bereits aktiv“ aber eigentlich abgelaufen
- Ursache: Housekeeping nicht gelaufen
- Prüfen:
  - `expireOldJobs()` wird in `createOpenJob()` am Anfang aufgerufen?

---

## 6) Logging & Nachvollziehbarkeit

Empfehlung:
- Logge auf API-Seite (info-level):
  - memberId, area, jobId, status transitions
  - deviceId, confirm outcome
- Für Audit:
  - Door Jobs Tabelle ist die primäre Quelle
  - optional separate Audit-Tabelle für „Tür tatsächlich geöffnet“ (falls Hardware Rückmeldung)

---

## 7) Quick Links (Dateien/Eintrittspunkte)

- `AccessController::open()` → PWA Entry
- `DeviceController::poll()` → Pi Pull
- `DeviceController::confirm()` → Pi Confirm
- `DoorJobService::*` → Business Logic + State Machine
- `AccessRequestService::sendOrResendDoiForArea()` → DOI Flow
- `tl_co_door_job` → Hauptprotokoll / Debugging

---

## 8) Offene TODOs (falls relevant)

- E2E Script an neue Confirm-Statuscodes (410/409/…) anpassen
- Admin-UI: Job-Log Filter/Export
- Monitoring/Alerting bei auffälligen Fehlversuchen
