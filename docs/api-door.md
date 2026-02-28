# Door API -- Zukunftwohnen Zugangssystem

> Server-API für das Türöffnungssystem (PWA → Contao → Raspberry Pi →
> Relais)

------------------------------------------------------------------------

## 1. Architekturüberblick

Das Zugangssystem besteht aus drei klar getrennten Rollen:

  -----------------------------------------------------------------------
  Rolle                         Funktion
  ----------------------------- -----------------------------------------
  **PWA (Mitglied)**            Fordert Türöffnung an

  **Contao API**                Validiert, verwaltet Door-Jobs, steuert
                                Zustände

  **Raspberry Pi (Device)**     Holt Jobs per Pull, öffnet Tür, sendet
                                Confirm
  -----------------------------------------------------------------------

### Kommunikationsprinzip

-   Pull-Modell (keine eingehenden Verbindungen am Pi)
-   Kein Webhook
-   Kein permanenter Socket
-   Polling mit kurzem Intervall

------------------------------------------------------------------------

## 2. Job-Lebenszyklus

Ein Door-Job durchläuft folgende Zustände:

pending → dispatched → executed\
                                                    ↘\
                                                    failed

Oder bei Timeout:

pending → expired\
dispatched → expired

------------------------------------------------------------------------

## 3. Zeitregeln

  Phase           Zeitsteuerung
  --------------- --------------------------------
  pending         `expiresAt` (Unix Timestamp)
  dispatched      `dispatchedAt + confirmWindow`
  confirmWindow   30 Sekunden

**Wichtig:**

-   `expiresAt` gilt nur für `pending`
-   `dispatched` wird ausschließlich über `dispatchedAt` geprüft
-   Confirm nach Ablauf → `410 confirm_timeout`

------------------------------------------------------------------------

## 4. Sicherheitsprinzipien

-   Frontend-Zugriff nur mit gültiger Contao-Session
-   Device-Authentifizierung über `deviceId` + Secret
-   RateLimit pro Mitglied + Area
-   Locking:
    -   Member+Area Lock
    -   Global Area Lock
-   Nonce pro Dispatch (Replay-Schutz)

------------------------------------------------------------------------

## 5. Ablaufdiagramm

``` mermaid
sequenceDiagram
    participant User as PWA (Mitglied)
    participant API as Contao API
    participant Pi as Raspberry Pi Device

    User->>API: POST /api/door/open/{area}
    API->>API: createOpenJob()
    API-->>User: 202 Accepted (jobId)

    loop Polling
        Pi->>API: GET /api/device/poll/{deviceId}
        API->>API: dispatchJobs()
        API-->>Pi: jobId + nonce
    end

    Pi->>API: POST /api/device/confirm/{deviceId}
    API->>API: confirmJob()

    alt innerhalb confirmWindow
        API-->>Pi: 200 executed/failed
    else Timeout (>30s)
        API-->>Pi: 410 confirm_timeout
    end
```
