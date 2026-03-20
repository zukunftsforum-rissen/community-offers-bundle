# Security Model -- Door Access System

## Ziel

Dieses System steuert physische Zugänge zu Gemeinschaftsräumen über eine bewusst asynchrone Architektur.  
Es vermeidet direkte Türöffnungsbefehle aus dem Frontend und reduziert dadurch die Angriffsfläche.

Der Ablauf ist:

member request  
→ door job created (`pending`)  
→ device poll  
→ job dispatch  
→ device confirm  
→ `executed` / `failed` / `expired`

## Authentifizierung

### Frontend

- Contao-Frontend-Session
- Zugriff nur für eingeloggte Mitglieder
- Türöffnung nur für Mitglieder mit passender Berechtigung für die angeforderte Area

### Device

- Geräte authentifizieren sich als eigene API-User
- jedes Device besitzt eine eindeutige `deviceId`
- Devices dürfen nur ihre zugewiesenen Areas pollen
- Confirm ist zusätzlich an die `deviceId` des Dispatch gebunden

## Schutzmechanismen

- Rate Limiting für Mitglieder bei Türöffnungen
- Area Lock
- Member+Area Lock
- Poll-Throttle für Devices
- Confirm Timeout (30s)
- Pending TTL / Expiry veralteter Jobs
- Workflow State Machine mit expliziten Endzuständen

## Dispatch-Sicherheit

Ein Job wird nur dann an ein Device übergeben, wenn:

- der Status `pending` ist
- die Area zum Device passt
- der Job noch nicht abgelaufen ist

Der Dispatch erfolgt atomar auf Datenbankebene.  
Dadurch können mehrere pollende Devices denselben Job nicht gleichzeitig übernehmen.

## Confirm-Sicherheit

Ein Confirm wird nur akzeptiert, wenn:

- der Job im Status `dispatched` ist
- die Nonce exakt passt
- die Nonce im constant-time compare geprüft wird
- das bestätigende Device der `dispatchToDeviceId` des Jobs entspricht
- das Confirm innerhalb des gültigen Zeitfensters eingeht

Abgelaufene oder bereits abgeschlossene Jobs werden nicht erneut bestätigt.

## Replay- und Manipulationsschutz

- pro Dispatch wird eine neue kryptographisch sichere Nonce erzeugt
- Nonce-Länge: 64 Hex-Zeichen
- Confirm nur mit korrekter `deviceId` + Nonce
- idempotente Bestätigung für bereits abgeschlossene Zustände wird kontrolliert behandelt
- ein Job kann nicht mehrfach erfolgreich bestätigt werden

## Logging & Audit

Das System protokolliert sicherheitsrelevante Ereignisse, insbesondere:

- Zeitpunkt der Anfrage
- Mitglied
- Area
- `dispatchToDeviceId`
- Statuswechsel
- `executedAt`
- Ergebniscode / Ergebnisnachricht

Alle Vorgänge tragen eine Correlation ID, damit der komplette Ablauf über Open, Poll, Dispatch und Confirm nachvollzogen werden kann.

## Datenminimierung und Aufbewahrung

Protokolldaten werden ausschließlich für:

- Betrieb
- Fehleranalyse
- Missbrauchserkennung
- Nachvollziehbarkeit sicherheitsrelevanter Vorgänge

gespeichert.

Empfohlene Aufbewahrung:

- Tür-Logs: 90 Tage
- erfolgreiche / abgelaufene Jobs: 30 Tage
- fehlgeschlagene Jobs: 180 Tage

## Produktionssicherheit

- Geräte sollten in einem getrennten Netz betrieben werden (z. B. VLAN / Gast-LAN)
- Secrets müssen regelmäßig rotieren
- Fehlversuche und ungewöhnliches Polling sollen überwacht werden
- in Produktion muss ein echtes `DoorGatewayInterface` gesetzt sein
- Mock-Gateways sind nur für Entwicklung und Tests vorgesehen

## Sicherheitsannahmen

Das System geht davon aus, dass:

- Frontend-Mitgliedskonten geschützt sind
- Device-Secrets nicht öffentlich werden
- der Raspberry Pi und das Türnetz logisch vom restlichen Netz getrennt sind
- TLS / HTTPS für den Zugriff auf die API verwendet wird

## Restrisiken

Wie bei jedem physischen Zugangssystem bleiben Restrisiken bestehen, insbesondere:

- kompromittierte Mitgliedskonten
- kompromittierte Geräte-Secrets
- physischer Zugriff auf Hardware
- Fehlkonfiguration der Produktionsumgebung

Diese Risiken werden durch Netztrennung, Logging, Rate Limits, Expiry und Device-Bindung reduziert, aber nicht vollständig eliminiert.