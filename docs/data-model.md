# Datenmodell (vollständig) -- Zukunftwohnen Zugangssystem

Dieses Dokument beschreibt **alle relevanten Tabellen** für das
Zugangssystem (Door, Devices, Areas, Access/DOI, Audit) sowie die
wichtigsten Beziehungen, Indizes und Debug-Queries.

> Hinweis: Einige Tabellennamen sind projektabhängig. Wo der exakte Name
> in deinem Bundle abweichen kann, ist er als **„(bei euch ggf. ...)"**
> markiert. In der IDE findest du die tatsächlichen Namen schnell über
> `tl_co_`-Suche oder in `Resources/contao/dca/*.php`.

------------------------------------------------------------------------

## 0) Überblick: Tabellen & Beziehungen

``` text
tl_member (Contao) 1 ---- n tl_co_door_job
                           |
                           |  n ---- 1  tl_co_device (optional)
                           |
                           |  n ---- 1  tl_co_area   (optional Konfig)
                           |
                           +-- n ---- 1  tl_co_access_request (optional DOI/Access)
                           |
                           +-- n ---- 1  tl_co_audit_log (optional)
```

**Minimal erforderlich** - `tl_member` (Contao Core,
Frontend-Mitglieder) - `tl_co_door_job` (Door Jobs / Zustandsmaschine)

**Sehr empfohlen** - `tl_co_device` (Devices + Secrets + erlaubte
Areas) - `tl_co_area` (Areas/Türen Konfiguration)

**Optional / je nach Feature** - `tl_co_access_request` (DOI / Access
Requests) - `tl_co_door_audit` oder `tl_co_audit_log` (stabiler
Audit-Trail / Auswertung)

------------------------------------------------------------------------

## 1) Contao Core Tabellen

### 1.1 `tl_member` (Contao)

**Zweck:** Frontend-Mitglieder. Wird für Auth + `memberId` genutzt.

**Relevante Felder (typisch)** - `id` (PK) - `email` - `firstname`,
`lastname` - Rollen/Flags (projektabhängig, z.B. „Kisten-Abo" als
Custom-Feld)

**Beziehung** - `tl_co_door_job.requestedByMemberId -> tl_member.id`

------------------------------------------------------------------------

## 2) Kern-Tabelle: Door Jobs

### 2.1 `tl_co_door_job`

**Zweck:** Zustandsmaschine für Türöffnungen (Request → Dispatch →
Confirm).

#### Statusmaschine

``` text
pending -> dispatched -> executed
                    \-> failed

pending -> expired
dispatched -> expired
```

#### Zeitregeln

-   `pending` läuft über `expiresAt` ab.
-   `dispatched` läuft über `dispatchedAt + confirmWindow` (30s) ab.
-   `executed/failed/expired` sind final.

#### Feld-Referenz (typisch)

  --------------------------------------------------------------------------
  Feld                    Typ              Bedeutung
  ----------------------- ---------------- ---------------------------------
  `id`                    int (PK)         Primärschlüssel

  `tstamp`                int              Contao Standard Timestamp

  `createdAt`             int              Job-Erstellung (Unix)

  `area`                  varchar          Area-Slug (`workshop`, `sharing`,
                                           `swap-house`, `depot`)

  `status`                varchar          `pending`, `dispatched`,
                                           `executed`, `failed`, `expired`

  `requestedByMemberId`   int              FE Member ID

  `requestIp`             varchar          IP der Anfrage

  `userAgent`             varchar          User-Agent (gekürzt)

  `expiresAt`             int              Pending TTL (nur `pending`)

  `dispatchToDeviceId`    varchar          Device-ID (String)

  `dispatchedAt`          int              Dispatch-Zeitpunkt

  `nonce`                 varchar          Dispatch-Nonce (Replay-Schutz)

  `attempts`              int              Dispatch-Versuche

  `executedAt`            int              Finaler Zeitpunkt

  `resultCode`            varchar          `OK`, `ERR`, `TIMEOUT`, ...

  `resultMessage`         varchar          Kurztext (ggf. gekürzte Meta)
  --------------------------------------------------------------------------

#### Invarianten

-   `expiresAt` nur relevant für `pending`.
-   Bei `dispatched`: `dispatchToDeviceId`, `dispatchedAt`, `nonce`
    müssen gesetzt sein.
-   Confirm darf nur akzeptiert werden, wenn `deviceId` + `nonce` passen
    (oder idempotent final).

#### Empfohlene Indizes

-   `(status, area, expiresAt)` → Poll/Dispatch Auswahl
-   `(status, dispatchedAt)` → Expire dispatched & Debug
-   `(requestedByMemberId, area, createdAt)` → Idempotenz/Reporting
-   `(dispatchToDeviceId, status)` → Device-spezifische Auswertungen
    (optional)

------------------------------------------------------------------------

## 3) Device Konfiguration (empfohlen)

### 3.1 `tl_co_device` (bei euch ggf. `tl_co_devices`)

**Zweck:** Registrierte Devices (Raspberry Pi), Secrets/Hashes, erlaubte
Areas.

**Typische Felder** \| Feld \| Bedeutung \| \|------\|----------\| \|
`id` \| PK (intern) \| \| `deviceId` \| String (öffentlich; im API-Pfad)
\| \| `secretHash` \| Hash des Secrets/Keys \| \| `isActive` \| Device
freigeschaltet \| \| `name` \| Anzeigename („Schuppen-Pi") \| \|
`allowedAreas` \| CSV/JSON oder relationale Zuordnung \| \| `lastSeenAt`
\| letzter Poll \| \| `createdAt` \| Erstellung \| \| `updatedAt` \|
Update \|

**Beziehung** -
`tl_co_door_job.dispatchToDeviceId -> tl_co_device.deviceId` (logisch,
nicht zwingend FK)

**Alternative Modellierung (sauberer)** - statt `allowedAreas` als Text:
relationale Mapping-Tabelle `tl_co_device_area`: - `deviceId`, `area`

------------------------------------------------------------------------

## 4) Areas / Türen Konfiguration (empfohlen)

### 4.1 `tl_co_area` (bei euch ggf. `tl_co_areas`)

**Zweck:** Konfiguration der Areas/Türen inkl. Regeln, Labels, ggf.
Hardware-Mapping.

**Typische Felder** \| Feld \| Bedeutung \| \|------\|----------\| \|
`id` \| PK \| \| `slug` \| `workshop`, `sharing`, ... \| \| `title` \|
Anzeige („Werkstatt") \| \| `minAge` \| z.B. 18 für Werkstatt (optional)
\| \| `requiresSubscriptionFlag` \| z.B. Depot nur mit Kisten-Abo
(optional) \| \| `isActive` \| aktiv/deaktiviert \| \| `deviceId` \|
Standard-Zieldevice (optional) \| \| `createdAt`, `updatedAt` \| Meta \|

**Beziehung** - `tl_co_door_job.area -> tl_co_area.slug` (logisch)

------------------------------------------------------------------------

## 5) Access / DOI Requests (optional Feature)

### 5.1 `tl_co_access_request` (bei euch ggf. `tl_co_doi_request`)

**Zweck:** Double-Opt-In Flow für Area-Zugriff / Mitgliedschaft /
Freischaltung.

**Typische Felder** \| Feld \| Bedeutung \| \|------\|----------\| \|
`id` \| PK \| \| `email` \| Empfänger \| \| `area` \| betroffene Area \|
\| `token` \| DOI Token \| \| `status` \| `requested`, `confirmed`,
`expired`, `revoked` \| \| `createdAt` \| \| \| `confirmedAt` \| \| \|
`expiresAt` \| Token TTL \| \| `memberId` \| verknüpfter `tl_member.id`
(optional) \| \| `meta` \| JSON/Text (optional) \|

**Service-Bezug** -
`AccessRequestService::sendOrResendDoiForArea(...)` - `confirm/verify`
Methoden (token→status=confirmed)

------------------------------------------------------------------------

## 6) Audit / Protokoll (optional aber stark empfohlen)

### 6.1 `tl_co_audit_log` (oder `tl_co_door_audit`)

**Zweck:** Stabiler Audit-Trail unabhängig von
DoorJob-Lifecycle/Retention.

**Warum?** - DoorJobs werden evtl. nach X Tagen gelöscht/archiviert. -
Audit soll ggf. länger verfügbar bleiben (Compliance/Transparenz).

**Typische Felder** \| Feld \| Bedeutung \| \|------\|----------\| \|
`id` \| PK \| \| `eventType` \| `door.requested`, `door.dispatched`,
`door.executed`, ... \| \| `jobId` \| Referenz auf `tl_co_door_job.id`
\| \| `area` \| slug \| \| `memberId` \| tl_member.id \| \| `deviceId`
\| deviceId \| \| `timestamp` \| Unix \| \| `correlationId` \| Trace-ID
(siehe Observability Doc) \| \| `payload` \| JSON (gekürzt) \|

------------------------------------------------------------------------

## 7) Debug-Queries (praktisch)

### 7.1 Letzte Jobs

``` sql
SELECT id, area, status, createdAt, expiresAt, dispatchedAt, executedAt, resultCode, resultMessage
FROM tl_co_door_job
ORDER BY id DESC
LIMIT 50;
```

### 7.2 Aktive pending Jobs

``` sql
SELECT id, area, createdAt, expiresAt
FROM tl_co_door_job
WHERE status='pending' AND expiresAt >= UNIX_TIMESTAMP()
ORDER BY createdAt ASC;
```

### 7.3 Aktive dispatched Jobs (confirmWindow)

``` sql
SELECT id, area, dispatchedAt, dispatchToDeviceId, nonce
FROM tl_co_door_job
WHERE status='dispatched' AND dispatchedAt >= UNIX_TIMESTAMP() - 30
ORDER BY dispatchedAt DESC;
```

### 7.4 Fehler/Timeouts letzte 24h

``` sql
SELECT area, resultCode, COUNT(*) AS cnt
FROM tl_co_door_job
WHERE createdAt >= UNIX_TIMESTAMP() - 86400
GROUP BY area, resultCode
ORDER BY cnt DESC;
```

------------------------------------------------------------------------

## 8) Retention / Cleanup

Empfehlung (Richtwert): - `pending`/`dispatched` sollten nie alt werden
(werden expired). - `executed/failed/expired` nach **90 Tagen** löschen
oder archivieren. - Audit-Log ggf. länger behalten (z.B. 12--24 Monate)
-- abhängig von Projektbedarf.

------------------------------------------------------------------------

## 9) Nächster Schritt zur 100%-Genauigkeit

Wenn du willst, kann ich das Dokument **1:1 auf eure echten Tabellen**
zuschneiden. Dafür bräuchte ich: - `Resources/contao/dca/*.php` (oder
die relevanten Ausschnitte) - ggf. SQL Install/Update Dateien (falls
vorhanden) - optional: `routes.yaml` (zur Vollständigkeit)
