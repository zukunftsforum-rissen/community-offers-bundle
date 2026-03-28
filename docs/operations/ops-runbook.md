# Ops Runbook — Zukunftwohnen Zugangssystem (Technik)

Diese Runbook-Datei beschreibt Betrieb, Fehlersuche und Wartung
des Zugangssystems (Contao API + Device Pull-Modell).

---

# 1) Systemzustand schnell prüfen (5‑Minuten‑Check)

## Server (Contao/Symfony)

Prüfen:

- API erreichbar:
  POST /api/door/open/{area}
  → liefert als eingeloggter FE‑User HTTP 202

- Device API erreichbar:
  POST /api/device/poll
  → liefert HTTP 200 (mit oder ohne Jobs)

- Datenbank erreichbar:
  Tabelle tl_co_door_job zeigt neue Einträge
  bei Türanforderungen.

---

## Device (Raspberry Pi oder Emulator)

Prüfen:

- Uhrzeit stimmt (NTP aktiv)
- Netzwerk vorhanden (HTTPS erreichbar)
- Poll‑Loop läuft kontinuierlich
- Poll‑Intervall: 2 Sekunden (fest definiert)
- Hardware lokal testbar (Relais/GPIO)

---

# 2) Door‑Flow — Beobachtbare Punkte

## A) PWA → Server (open)

Erwartung:

HTTP 202 mit:

- jobId
- status=pending

Typische Fehler:

401  
→ User nicht eingeloggt

429  
→ RateLimit aktiv  
→ retryAfterSeconds beachten

---

## B) Device → Server (poll)

Erwartung:

jobs[] mit:

- jobId
- area
- nonce
- expiresInMs (~ confirmWindow)

Wenn keine Jobs vorhanden:

Prüfen:

- Existiert pending Job in DB?
- Stimmen die Device‑Areas?
- Ist Device korrekt authentifiziert?

---

## C) Device → Server (confirm)

Erwartung:

HTTP 200:

- executed
oder
- failed

Bei abgelaufenem Job:

HTTP 410:

expired

Nicht:

confirm_timeout

(aktuelle Terminologie ist expired)

---

# 3) Standard‑Fehlerbilder & Fixes

## 3.1 Job läuft ab (status=expired)

Symptom:

DB:

status=expired

Typische Ursachen:

- confirm zu spät gesendet
- Device blockiert
- Netzwerkproblem
- Poll läuft, aber Aktion dauert zu lange

Checkliste:

- confirmDelay < confirmWindow?
- CPU‑Last prüfen
- Netzwerk prüfen
- NTP aktiv?

Fix:

- confirm sofort senden
- Hardware‑Verzögerung reduzieren
- optional confirmWindow erhöhen
  (nur nach Sicherheitsabwägung)

---

## 3.2 Nonce / Device mismatch (403)

Ursachen:

- falsches deviceId
- falscher Token
- veralteter nonce
- Job bereits ersetzt

Fix:

- Poll‑Ergebnis immer direkt verwenden
- confirm sofort senden
- keine Zwischenspeicherung

---

## 3.3 Jobs bleiben aktiv

Mögliche Ursache:

expireOldJobs() wird nicht regelmäßig ausgeführt.

Sollte erfolgen in:

- createOpenJob()
- dispatchJobs()

---

## 3.4 Tür „in Benutzung" (Locks)

Ursache:

Member‑ oder Area‑Lock aktiv.

Fix:

- retryAfterSeconds beachten
- Cache prüfen
- TTL prüfen
- Server‑Zeit prüfen

---

# 4) Datenbank — wichtigste Queries

## Letzte Jobs

```sql
SELECT id,
       area,
       status,
       createdAt,
       expiresAt,
       dispatchedAt,
       executedAt,
       resultCode,
       resultMessage
FROM tl_co_door_job
ORDER BY id DESC
LIMIT 50;
```

---

## Aktive pending Jobs

```sql
SELECT id,
       area,
       createdAt,
       expiresAt
FROM tl_co_door_job
WHERE status='pending'
  AND expiresAt >= UNIX_TIMESTAMP()
ORDER BY createdAt ASC;
```

---

## Aktive dispatched Jobs

```sql
SELECT id,
       area,
       dispatchedAt,
       dispatchToDeviceId
FROM tl_co_door_job
WHERE status='dispatched'
  AND dispatchedAt >= UNIX_TIMESTAMP() - 30
ORDER BY dispatchedAt DESC;
```

Hinweis:

30 Sekunden entspricht typischerweise:

confirmWindow

---

# 5) Logging (Empfehlung)

## Server

Loggen:

- memberId
- area
- jobId
- status transitions
- deviceId
- confirm outcome
- error codes
- rate limit events

---

## Device

Pro Loop:

- poll.start
- poll.end
- job count

Pro Job:

- jobId
- area
- action start/end
- confirm result
- hardware errors

---

# 6) Wartung

## Token Rotation (Device)

Empfehlung:

- Token regelmäßig erneuern
- z. B. alle 3–6 Monate

Vorgehen:

- neuen Token generieren
- Hash aktualisieren
- Device neu starten

---

## Housekeeping / DB Growth

Empfehlung:

- alte Jobs entfernen
- z. B. > 90 Tage

Implementierung:

- Cronjob oder Command

---

# 7) Incident Playbook („Tür geht nicht")

Schritte:

1. open → HTTP 202?
2. DB → pending Job vorhanden?
3. Device → sieht Job?
4. confirm → HTTP 200?
5. Hardware → Relais schaltet?
6. Netzwerk → DNS / HTTPS ok?

Analyse:

Wenn Schritt 2 ok, aber 3 nicht:

→ Device Auth prüfen  
→ Areas prüfen  
→ Poll prüfen  

Wenn Schritt 3 ok, aber 4 fehlschlägt:

→ confirmWindow prüfen  
→ nonce prüfen  
→ Device‑Delay prüfen
