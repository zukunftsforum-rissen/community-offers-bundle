# Tracing & Observability -- Zukunftwohnen Zugangssystem

Dieses Dokument beschreibt, wie du nach Monaten schnell
nachvollziehst: - **Was ist passiert?** - **Warum ist es passiert?** -
**Wo ist es kaputt gegangen (PWA / API / Pi / Hardware)?**

Ziel: reproduzierbare Fehlersuche + aussagekräftige Logs + einfache
Metriken.

------------------------------------------------------------------------

## 1) Grundprinzip: Korrelation (Correlation-ID / Trace-ID)

**Jeder Öffnungsvorgang** erhält eine **Correlation-ID**, die sich
durchzieht: - PWA Request (open) - API Logs / DB (jobId) - Pi Logs
(poll/confirm) - optional Audit-Log Tabelle

### Empfohlene ID

-   `X-Correlation-Id`: UUID v4 oder ULID
-   Wenn Client keine liefert, erzeugt die API eine und gibt sie zurück.

**Response-Feld**

``` json
{ "correlationId": "01J...ULID", "jobId": 123, ... }
```

**DB** - optional Feld `correlationId` in `tl_co_door_job` - oder nur im
Audit-Log speichern

------------------------------------------------------------------------

## 2) Logging -- Minimalformat (Server)

### Format (empfohlen: JSON lines)

Pro Log-Event ein JSON-Objekt (leicht grepbar + ingest in Loki/ELK).

**Felder** - `ts` (timestamp) - `level` (info/warn/error) - `event`
(z.B. `door.open.accepted`, `door.dispatch.claimed`,
`door.confirm.timeout`) - `correlationId` - `jobId` - `area` -
`memberId` - `deviceId` - `httpStatus` - `errorCode` (z.B.
`confirm_timeout`, `forbidden`, `rate_limited`) - `elapsedMs`
(Request-Latenz) - `meta` (kleines JSON; keine PII)

### Beispiel

``` json
{"ts":"2026-02-28T18:05:13Z","level":"info","event":"door.open.accepted","correlationId":"01J...","jobId":123,"area":"workshop","memberId":42,"httpStatus":202,"elapsedMs":18}
```

------------------------------------------------------------------------

## 3) Logging -- Minimalformat (Raspberry Pi)

**Pro Poll-Zyklus** - `poll.start`, `poll.end` - `jobs.count`

**Pro Job** - `job.received` (jobId, area, nonceHash) -
`job.action.start` / `job.action.end` (duration) - `job.confirm.sent` /
`job.confirm.result` (status, error)

> Nonce nicht im Klartext loggen, sondern z.B. SHA-256(Nonce) oder kurz
> gekürzt.

------------------------------------------------------------------------

## 4) Metriken (auch ohne Prometheus nützlich)

Selbst ohne Metrics-Stack kannst du die wichtigsten Kennzahlen per
DB/Logs gewinnen.

### 4.1 Server-KPIs (pro Tag/Stunde)

-   `door.open.accepted.count`
-   `door.open.rate_limited.count` (429)
-   `door.dispatch.claimed.count`
-   `door.confirm.ok.count`
-   `door.confirm.forbidden.count` (403)
-   `door.confirm.timeout.count` (410)
-   `door.jobs.expired.count`

### 4.2 Pi-KPIs

-   Poll-Frequenz (Durchschnitt)
-   Confirm-Latenz (poll→confirm)
-   Hardware-Fehlerquote (Relay failures)
-   Connectivity errors (DNS/TLS/timeout)

------------------------------------------------------------------------

## 5) Alerts (minimal sinnvoll)

Wenn du Alerts willst, starte klein:

-   **Confirm timeout spike**
    -   `410 confirm_timeout` über Schwellwert → Pi offline/zu langsam
-   **Forbidden spike**
    -   viele `403 forbidden` → Secret falsch/Attacke/Clock skew/Nonce
        mismatch
-   **Rate limit spike**
    -   viele `429` → UI-Spam oder Bedienproblem

------------------------------------------------------------------------

## 6) Debugging-Leitfaden (mit Correlation-ID)

1.  User meldet Problem + Zeitpunkt + Area
2.  Suche in Server-Logs nach `area` + Zeitfenster → finde
    `correlationId`/`jobId`
3.  DB: `SELECT * FROM tl_co_door_job WHERE id=?`
4.  Pi-Logs: suche `jobId` oder `correlationId`
5.  Prüfe Kante:
    -   open ok?
    -   dispatch erfolgt?
    -   confirm gesendet?
    -   confirm akzeptiert?
    -   Hardware hat geschaltet?

------------------------------------------------------------------------

## 7) Praktische Implementationshinweise

### 7.1 Correlation-ID in Symfony

-   Middleware/EventSubscriber, der:
    -   eingehenden Header liest oder neu erzeugt
    -   in `Request` Attribute und Logger Context legt
    -   in Responses zurückschreibt

### 7.2 DB-Erweiterung (optional)

-   `ALTER TABLE tl_co_door_job ADD correlationId VARCHAR(32) DEFAULT '';`
-   Index auf correlationId (optional)

### 7.3 Audit Table (optional)

-   Schreibe Events in `tl_co_audit_log` (eventType + payload)
-   Retention getrennt von DoorJobs

------------------------------------------------------------------------

## 8) Datenschutz / PII

-   Keine vollständigen E-Mails/IPs in Logs (oder nur gekürzt/hashed)
-   User-Agent ggf. gekürzt
-   Audit-Log bewusst gestalten (Transparenz vs. Datenminimierung)

------------------------------------------------------------------------

## 9) Checkliste „bereit für Betrieb"

-   [ ] Correlation-ID durchgehend (PWA→API→Pi)
-   [ ] Confirm liefert eindeutige HTTP-Codes (200/403/404/409/410)
-   [ ] Server+Pi Logs sind konsistent & grepbar
-   [ ] DB-Queries für Debug dokumentiert
-   [ ] Retention & Cleanup definiert
