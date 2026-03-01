# Security Model -- Door Access System

## Authentifizierung

### Frontend

-   Contao Frontend Session
-   Zugriff nur für eingeloggte Mitglieder

### Device

-   deviceId + Secret
-   Nonce pro Dispatch (Replay-Schutz)

## Schutzmechanismen

-   Rate Limiting (Mitglied + Area)
-   Area Lock
-   Member+Area Lock
-   Confirm Timeout (30s)
-   Pending TTL

## Replay & Manipulation Schutz

-   Nonce pro Dispatch
-   Confirm nur mit korrekter deviceId + nonce
-   Idempotente Bestätigung erlaubt

## Logging & Audit

-   Zeitpunkt der Anfrage
-   dispatchToDeviceId
-   executedAt
-   resultCode
-   resultMessage

## Empfehlungen

-   Separate VLAN / Gast-LAN für Pi
-   Regelmäßige Secret-Rotation
-   Monitoring von Fehlversuchen
