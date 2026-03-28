# Security Model — Door Access System

## Ziel

Dieses System steuert physische Zugänge zu Gemeinschaftsräumen
über eine bewusst asynchrone Architektur.

Es vermeidet direkte Türöffnungsbefehle aus dem Frontend
und reduziert dadurch die Angriffsfläche.

Ablauf:

member request  
→ door job created (`pending`)  
→ device poll  
→ job dispatch  
→ device confirm  
→ `executed` / `failed` / `expired`

---

# Authentifizierung

## Frontend

- Contao Frontend Session
- Zugriff nur für eingeloggte Mitglieder
- Türöffnung nur für Mitglieder mit passender Area-Berechtigung
- Zugriff basiert auf Gruppen-Zuordnung zu Areas

---

## Device

- Geräte authentifizieren sich als eigene API-User
- jedes Device besitzt eine eindeutige `deviceId`
- Geräte authentifizieren sich per Token (Hash gespeichert)
- Devices dürfen nur ihre zugewiesenen Areas pollen
- Confirm ist an die `dispatchToDeviceId` gebunden

Wichtig:

Polling erfolgt **immer aktiv durch das Device**
(im festen Intervall, typischerweise 2 Sekunden).

Es existieren **keine eingehenden Verbindungen**
zum Device.

---

# Schutzmechanismen

Das System verwendet mehrere Schutzschichten:

- Rate Limiting für Mitglieder
- Area Lock
- Member+Area Lock
- Poll-Throttle für Devices
- Confirm Window (z. B. 30 Sekunden)
- Pending TTL / Job Expiry
- Workflow State Machine mit Endzuständen

Terminologie:

Status bei Zeitüberschreitung:

expired

(nicht timeout)

---

# Dispatch-Sicherheit

Ein Job wird nur dann an ein Device übergeben, wenn:

- Status = `pending`
- Area gehört zum Device
- Job ist noch gültig (nicht expired)

Der Dispatch erfolgt:

- atomar auf Datenbankebene
- mit eindeutiger Zuweisung (`dispatchToDeviceId`)

Dadurch können mehrere pollende Devices
denselben Job nicht gleichzeitig übernehmen.

---

# Confirm-Sicherheit

Ein Confirm wird nur akzeptiert, wenn:

- Status = `dispatched`
- Nonce exakt passt
- Nonce wird mit constant-time compare geprüft
- Device entspricht `dispatchToDeviceId`
- Confirm erfolgt innerhalb des confirmWindow

Abgelaufene Jobs liefern:

HTTP 410  
Status:

expired

Nicht:

confirm_timeout

---

# Replay- und Manipulationsschutz

Pro Dispatch:

- neue kryptographisch sichere Nonce
- Nonce Länge: 64 Hex-Zeichen
- Bindung an deviceId
- Bindung an Job-Zustand

Zusätzlich:

- Confirm ist zustandsabhängig
- doppelte Confirms werden kontrolliert behandelt
- mehrfaches erfolgreiches Confirm ist nicht möglich

---

# Logging & Audit

Sicherheitsrelevante Ereignisse werden protokolliert.

Typische Audit-Felder:

- timestamp
- memberId
- area
- deviceId
- jobId
- status transition
- executedAt
- resultCode
- resultMessage
- correlationId

Correlation-ID:

Verbindet:

open → poll → dispatch → confirm

Damit ist der vollständige Ablauf nachvollziehbar.

Primäre Speicherung:

`tl_co_door_log`

---

# Datenminimierung und Aufbewahrung

Logs werden gespeichert für:

- Betrieb
- Fehleranalyse
- Missbrauchserkennung
- Nachvollziehbarkeit

Empfohlene Retention:

- Tür-Logs: 90 Tage
- erfolgreiche Jobs: 30 Tage
- fehlgeschlagene Jobs: 180 Tage
- expired Jobs: 30–90 Tage

Personenbezogene Daten:

Sollten minimiert werden.

Beispiele:

- keine vollständigen IP-Adressen
- keine Klartext-Secrets
- reduzierte User-Agent-Daten

---

# Produktionssicherheit

Empfehlungen:

- Geräte in getrenntem Netz betreiben
  (z. B. VLAN oder Gast-LAN)

- HTTPS zwingend verwenden

- Secrets regelmäßig rotieren

- ungewöhnliches Polling überwachen

- Emulator-Gateways nicht produktiv verwenden

In Produktion muss:

Ein echtes Hardware-Gateway aktiv sein.

---

# Sicherheitsannahmen

Dieses Modell setzt voraus:

- Frontend-Mitgliedskonten sind geschützt
- Device-Secrets bleiben geheim
- Device-Netz ist logisch getrennt
- TLS/HTTPS ist korrekt konfiguriert
- Server-Zeit ist korrekt synchronisiert (NTP)

Zeitabweichungen können:

- Confirm fehlschlagen lassen
- Jobs fälschlich expirieren lassen

---

# Restrisiken

Wie bei jedem physischen Zugangssystem:

Es bleiben Risiken bestehen.

Beispiele:

- kompromittierte Mitgliedskonten
- kompromittierte Device-Secrets
- physischer Zugriff auf Hardware
- Fehlkonfiguration im Netzwerk
- falsche Zeitsynchronisation

Diese Risiken werden reduziert durch:

- Netztrennung
- Logging
- Expiry
- Rate Limits
- Device-Bindung

Aber:

Sie können nicht vollständig eliminiert werden.
