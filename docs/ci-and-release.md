# CI & Release -- Zukunftwohnen Zugangssystem (Technik)

Diese Datei beschreibt eine schlanke Strategie, um das Zugangssystem
reproduzierbar zu testen und auszurollen.

------------------------------------------------------------------------

## 1) Test-Pyramide

### Unit/Service Tests (PHPUnit)

-   DoorJobService:
    -   `createOpenJob()` Idempotenz (pending/dispatched)
    -   RateLimit & Locks
    -   `expireOldJobs()` (pending + dispatched)
    -   `confirmJob*()` Codes: 200/403/404/409/410

### Integration Tests (Kernel / DB)

-   Real DB (sqlite/mysql) mit Fixture für `tl_co_door_job`.
-   Controller Response Contracts.

### E2E Smoke Test (Shell Script)

-   `e2e_device_test_all.sh`:
    -   open → poll → confirm
    -   Timeout confirm (delay \> 30)
    -   Bad token → 401
    -   Cleanup

------------------------------------------------------------------------

## 2) DDEV / Local Pipeline (Empfehlung)

### Make Targets (Beispiel)

-   `make test` → Unit + Integration
-   `make e2e` → DDEV up + e2e script
-   `make lint` → php-cs-fixer / phpstan

------------------------------------------------------------------------

## 3) Release Checklist (Prod)

1.  Deploy Code (Bundle/Composer)
2.  Cache warmup / clear (Contao)
3.  DB migrations / schema update (falls vorhanden)
4.  Sanity:
    -   open endpoint (PWA)
    -   device poll
    -   confirm
5.  Monitoring:
    -   Fehlerquote confirm (403/410) beobachten
    -   429 spikes (RateLimit) prüfen

------------------------------------------------------------------------

## 4) Observability (Minimum)

-   Server logs: structured (JSON) oder klar parsebar
-   Job table als Audit Trail
-   Optional:
    -   Metrics: count by status/error (executed/failed/expired,
        410/403/429)
    -   Alert wenn `expired` stark ansteigt (Pi offline oder
        Timing-Problem)
