# Ops Runbook -- Zukunftwohnen Zugangssystem (Technik)

Diese Runbook-Datei beschreibt Betrieb, Fehlersuche und Wartung des
Zugangssystems (Contao API + Raspberry Pi Pull-Modell).

------------------------------------------------------------------------

## 1) Systemzustand schnell prüfen (5-Minuten-Check)

### Server (Contao/Symfony)

-   **API erreichbar**: `/api/door/open/{area}` liefert als eingeloggter
    FE-User `202`.
-   **Device API erreichbar**: `/api/device/poll/{deviceId}` liefert
    `200` (mit/ohne Jobs).
-   **DB erreichbar**: `tl_co_door_job` wächst bei Requests und zeigt
    frische `createdAt`/`tstamp`.

### Raspberry Pi

-   Uhrzeit stimmt (NTP aktiv).
-   Netzwerk: Pi hat Internetzugang (HTTPS zu Server).
-   Poll-Loop läuft (Service/Timer oder Cron).
-   GPIO/Relais steuerbar (lokaler Hardwaretest).

------------------------------------------------------------------------

## 2) Door-Flow -- Beobachtbare Punkte

### A) PWA → Server (open)

**Erwartung** - HTTP `202` mit `jobId` und `status`.

**Wenn 401** - User nicht eingeloggt oder Cookies fehlen (curl ohne
Session).

**Wenn 429** - RateLimit oder Locks greifen: `retryAfterSeconds`
beachten.

### B) Pi → Server (poll)

**Erwartung** - `jobs[]` mit `jobId`, `area`, `nonce`, `expiresInMs` (≈
30000).

**Wenn keine Jobs** - Prüfe: Gibt es `pending` Jobs in DB? - Prüfe:
Pollen die richtigen `areas`?

### C) Pi → Server (confirm)

**Erwartung** - 200 `executed`/`failed` (oder idempotent 200). -
Timeout: 410 `confirm_timeout`.

------------------------------------------------------------------------

## 3) Standard-Fehlerbilder & Fixes

### 3.1 Confirm Timeout (Job wird `expired`)

**Symptom** - DB: `status=expired`, `resultCode=TIMEOUT`,
`Confirm timeout` - API: 410 `confirm_timeout`

**Checkliste** - confirmDelay (Log) \< 30s? - Poll-Intervall zu hoch? -
Pi CPU/IO blockiert? - Pi-Uhrzeit korrekt? (NTP)

**Fix** - Poll häufiger; Confirm sofort nach Aktion. - Optional:
confirmWindow erhöhen (Sicherheitsabwägung!).

### 3.2 Nonce/Device mismatch (403)

**Ursachen** - falsches deviceId/secret - falscher nonce (Copy/Paste,
stale data) - Job bereits neu dispatched (neuer nonce)

**Fix** - Poll-Ergebnis als Quelle der Wahrheit. - Confirm unmittelbar
nach Aktion, keine Zwischenspeicherung.

### 3.3 Jobs bleiben „aktiv", obwohl abgelaufen

**Ursache** - `expireOldJobs()` wird nicht regelmäßig aufgerufen.

**Fix** - Sicherstellen: `expireOldJobs()` am Anfang von
`createOpenJob()` und `dispatchJobs()`.

### 3.4 Tür „in Benutzung" (Locks)

**Ursache** - Member+Area Lock oder Area Lock aktiv (Cache TTL).

**Fix** - `retryAfterSeconds` respektieren. - Cache-Backend prüfen
(PSR-6) und Uhrzeit/TTL.

------------------------------------------------------------------------

## 4) Datenbank -- wichtigste Queries (Debug)

> Tabellen-/Feldnamen können abweichen; Kern ist `tl_co_door_job`.

### Letzte Jobs

``` sql
SELECT id, area, status, createdAt, expiresAt, dispatchedAt, executedAt, resultCode, resultMessage
FROM tl_co_door_job
ORDER BY id DESC
LIMIT 50;
```

### Aktive pending Jobs

``` sql
SELECT id, area, createdAt, expiresAt
FROM tl_co_door_job
WHERE status='pending' AND expiresAt >= UNIX_TIMESTAMP()
ORDER BY createdAt ASC;
```

### Aktive dispatched Jobs (innerhalb confirmWindow)

``` sql
SELECT id, area, dispatchedAt, dispatchToDeviceId
FROM tl_co_door_job
WHERE status='dispatched' AND dispatchedAt >= UNIX_TIMESTAMP()-30
ORDER BY dispatchedAt DESC;
```

------------------------------------------------------------------------

## 5) Logging (Empfehlung)

### Server

Logge pro Request mindestens: - `memberId`, `area`, `jobId`,
Statuswechsel - `deviceId`, `jobId`, confirm outcome, error codes -
RateLimit/Lock Ereignisse

### Pi

Logge pro Loop: - Poll start/end, Anzahl Jobs - Für jeden Job: jobId,
area, action start/end, confirm result - Hardwarefehler (GPIO/Relais)

------------------------------------------------------------------------

## 6) Wartung

### Secret Rotation (Device)

-   Device Secret/Hash regelmäßig wechseln (z.B. 3--6 Monate).
-   Rollout: Server akzeptiert kurzzeitig alt+neu (Grace Period).

### Housekeeping / DB Growth

-   Periodischer Cleanup alter Jobs (z.B. \> 90 Tage) per Cron/Command.

------------------------------------------------------------------------

## 7) Incident Playbook (wenn „Tür geht nicht")

1.  PWA open: kommt `202`?
2.  DB: existiert `pending` Job?
3.  Pi poll: sieht Pi den Job (richtiges area)?
4.  Pi confirm: kommt `200` oder `410/403/409`?
5.  Hardwaretest: Relais lokal schaltet?
6.  Netzwerk: Pi DNS/HTTPS ok?

Wenn Schritt 2 ok aber 3 nicht: - Device Auth, areas, Poll-URL prüfen.
Wenn Schritt 3 ok aber 4 fail: - confirmWindow, nonce/device mismatch,
Pi delay prüfen.
