# Tracing & Observability — Zukunftwohnen Zugangssystem

Dieses Dokument beschreibt, wie Fehlersuche strukturiert
und reproduzierbar durchgeführt werden kann.

Ziel:

- nachvollziehen: Was ist passiert?
- verstehen: Warum ist es passiert?
- lokalisieren: Wo ist es kaputt gegangen?

(PWA / API / Device / Hardware)

---

# 1) Grundprinzip: Korrelation (Correlation-ID)

Jeder Öffnungsvorgang erhält eine **Correlation-ID**.

Diese wird durchgängig verwendet in:

- API Requests
- Audit Logs (`tl_co_door_log`)
- Workflow Timeline
- Device Logs (optional)

---

## ID-Verwendung

Empfohlen:

Header:

X-Correlation-Id

Format:

UUID v4 oder ULID

Wenn kein Header vorhanden ist:

Die API erzeugt automatisch eine neue Correlation-ID.

Diese wird:

- intern gespeichert
- in Responses zurückgegeben
- in Logs geschrieben

---

## Response-Beispiel

```json
{
  "correlationId": "01J...ULID",
  "jobId": 123
}
```

---

## Speicherung

Correlation-ID wird gespeichert in:

- `tl_co_door_log`

Optional möglich:

- zusätzliches Feld in `tl_co_door_job`

Hinweis:

Ein separates Feld in `tl_co_door_job`
ist **nicht zwingend erforderlich**, da
`tl_co_door_log` bereits ausreichend ist.

---

# 2) Server Logging — Minimalformat

Empfohlenes Format:

JSON Lines (eine Zeile pro Event)

Typische Felder:

- ts (timestamp)
- level (info/warn/error)
- event
- correlationId
- jobId
- area
- memberId
- deviceId
- httpStatus
- errorCode
- elapsedMs
- meta (kein PII)

---

## Beispiel

```json
{"ts":"2026-02-28T18:05:13Z","level":"info","event":"door.open.accepted","correlationId":"01J...","jobId":123,"area":"workshop","memberId":42,"httpStatus":202,"elapsedMs":18}
```

---

# 3) Device Logging — Minimalformat

Pro Poll-Zyklus:

- poll.start
- poll.end
- jobs.count

Pro Job:

- job.received
- job.action.start
- job.action.end
- job.confirm.sent
- job.confirm.result

Wichtig:

Nonce darf nicht im Klartext geloggt werden.

Empfohlen:

- SHA-256 Hash
- oder gekürzte Darstellung

---

# 4) Metriken

Auch ohne Metrics-System sinnvoll.

---

## 4.1 Server-KPIs

Beispiele:

- door.open.accepted.count
- door.open.rate_limited.count
- door.dispatch.claimed.count
- door.confirm.ok.count
- door.confirm.forbidden.count
- door.jobs.expired.count

Wichtig:

Begriff:

expired

(nicht timeout)

---

## 4.2 Device-KPIs

Typische Kennzahlen:

- Poll-Frequenz
- Confirm-Latenz
- Hardware-Fehlerquote
- Netzwerkfehler (DNS/TLS)

---

# 5) Alerts (optional)

Minimal sinnvolle Alerts:

Confirm-Probleme:

Viele fehlgeschlagene Confirm-Vorgänge
→ Device möglicherweise offline

Forbidden-Spikes:

Viele 403-Antworten
→ falsches Token oder Sicherheitsproblem

Rate-Limit-Spikes:

Viele 429-Antworten
→ möglicherweise Fehlbedienung oder Angriff

---

# 6) Debugging-Leitfaden

Standard-Ablauf:

1. Zeitpunkt und Area ermitteln
2. Server-Logs durchsuchen
3. correlationId identifizieren
4. Audit-Logs prüfen
5. Device-Logs prüfen

Typische Prüfpunkte:

- Open erfolgreich?
- Dispatch erfolgt?
- Confirm gesendet?
- Confirm akzeptiert?
- Hardware ausgelöst?

---

# 7) Implementationshinweise

## 7.1 Symfony Integration

Empfohlen:

EventSubscriber oder Middleware:

Aufgaben:

- Header lesen oder erzeugen
- Correlation-ID speichern
- Logger-Kontext erweitern
- Response-Header setzen

---

## 7.2 Audit Logging

Alle Workflow-Ereignisse sollten:

- timestamped
- correlation-aware
- nachvollziehbar

sein.

Retention:

Sollte konfiguriert werden.

Beispiel:

30–90 Tage je nach Nutzung.

---

# 8) Datenschutz

Wichtige Regeln:

- Keine vollständigen E-Mail-Adressen loggen
- Keine vollständigen IP-Adressen speichern
- User-Agent ggf. kürzen
- Minimierung personenbezogener Daten

---

# 9) Betriebs-Checkliste

Empfohlen vor Produktionsstart:

[ ] Correlation-ID durchgehend aktiv  
[ ] Confirm liefert korrekte HTTP-Codes  
[ ] Logs sind konsistent und auswertbar  
[ ] Debug-Queries dokumentiert  
[ ] Retention-Regeln definiert  
