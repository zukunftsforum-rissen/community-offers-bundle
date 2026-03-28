# CI & Release — Zukunftwohnen Zugangssystem (Technik)

Diese Datei beschreibt eine schlanke Strategie, um das Zugangssystem
reproduzierbar zu testen, zu prüfen und sicher auszurollen.

---

# 1) Test-Pyramide

## Unit / Service Tests (PHPUnit)

Kernbereiche:

DoorJobService:

- createOpenJob()
  - Idempotenz (pending/dispatched)
  - RateLimit-Verhalten
  - Lock-Logik
- expireOldJobs()
  - pending → expired
  - dispatched → expired
- confirmJob()
  - Statuscodes korrekt:
    - 200 executed / failed
    - 403 forbidden
    - 404 not_found
    - 409 invalid_state
    - 410 expired

Wichtig:

Status:

expired

(nicht timeout)

---

## Integration Tests (Kernel / DB)

Ziel:

Reales Zusammenspiel testen.

Empfohlen:

- SQLite oder MySQL Testdatenbank
- Fixture für tl_co_door_job
- Controller Response Contracts prüfen

Typische Tests:

- POST /api/door/open/{area}
- POST /api/device/poll
- POST /api/device/confirm

---

## E2E Smoke Test (Shell Script)

Beispiel:

e2e_device_test_all.sh

Typische Abläufe:

1. open → Job erstellt
2. poll → Job erhalten
3. confirm → executed
4. verspätetes confirm → expired (410)
5. falscher Token → 401
6. Cleanup

Wichtig:

Poll erfolgt aktiv durch Device.

---

# 2) Lokale Pipeline (z. B. DDEV)

Empfohlene Make Targets:

make test  
→ Unit + Integration Tests

make e2e  
→ DDEV starten  
→ E2E Script ausführen

make lint  
→ php-cs-fixer  
→ phpstan

Optional:

make qa  
→ vollständige Qualitätsprüfung

---

# 3) CI Pipeline (Empfehlung)

Typisch:

GitHub Actions oder vergleichbar.

Minimaler Workflow:

1. Composer install
2. PHPStan
3. PHPUnit
4. Coding Style Check
5. Optional: Security Scan

Beispiel-Jobs:

- php-8.4-test
- static-analysis
- coding-style

Wichtig:

CI muss vor jedem Merge erfolgreich sein.

---

# 4) Release Checklist (Produktion)

Vor jedem Release:

1. Version taggen  
   Beispiel:

   v1.2.0

2. Code deployen  
   (Composer Update oder Bundle Deployment)

3. Cache aktualisieren

   contao-console cache:clear

4. Datenbank prüfen

   Schema Update falls nötig.

5. Sanity Checks:

   - open funktioniert
   - poll funktioniert
   - confirm funktioniert

---

# 5) Post-Release Monitoring

Nach Deployment:

Überwachen:

- confirm Fehler (403 / 410)
- expired Jobs
- RateLimit Events (429)

Warnsignal:

Viele expired Jobs können bedeuten:

- Device offline
- Netzwerkproblem
- Zeitabweichung

---

# 6) Observability (Minimum)

Empfohlen:

Server Logs:

- strukturiert (JSON)
- oder klar parsebar

Primäre Datenquelle:

tl_co_door_job  
tl_co_door_log

Optional:

Metrics:

- count by status:
  - executed
  - failed
  - expired

Alerts:

- expired steigt stark an
- 403-Spikes
- 429-Spikes

---

# 7) Rollback-Strategie

Empfohlen:

Rollback muss jederzeit möglich sein.

Typischer Ablauf:

1. Vorherige Version erneut deployen
2. Cache neu aufbauen
3. System erneut testen

Wichtig:

- Releases immer versionieren
- keine ungeprüften Änderungen direkt deployen

---

# 8) Versionierung

Empfohlen:

Semantic Versioning

Schema:

MAJOR.MINOR.PATCH

Beispiele:

v1.0.0  
v1.1.0  
v1.1.1

Regeln:

PATCH:

Bugfix

MINOR:

Neue Features ohne Breaking Changes

MAJOR:

Breaking Changes

