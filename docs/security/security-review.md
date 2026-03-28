# Security Review — Door Access System

Dieses Dokument beschreibt eine kompakte sicherheitstechnische
Überprüfung zentraler Systemmechanismen.

---

# Pull Architecture

Das System verwendet ein Pull-Modell:

Devices pollen den Server aktiv.

Wichtig:

- keine eingehenden Netzwerkverbindungen zu Devices
- keine Remote-Trigger von außen
- Device initiiert jede Kommunikation

Dies reduziert die Angriffsfläche erheblich.

Polling erfolgt regelmäßig
(typischerweise alle 2 Sekunden).

---

# Authentication

## Device Authentication

Devices authentifizieren sich über:

- deviceId
- Device-Token (Hash gespeichert)

Mechanismus:

- Token wird im Klartext übertragen
- Server speichert ausschließlich Hash
- Vergleich erfolgt gegen gespeicherten Hash

Sicherheitsvorteil:

Ein kompromittierter Datenbank-Dump
enthält keine nutzbaren Klartext-Tokens.

---

# Nonce Protection

Jeder Dispatch enthält:

- eine kryptographisch sichere Nonce
- Nonce-Länge: 64 Hex-Zeichen

Das Device muss diese Nonce beim Confirm
zurückgeben.

Validierung:

- Nonce wird im constant-time compare geprüft
- Nonce ist an den Job gebunden
- Nonce ist an das Device gebunden

Schutz gegen:

- Replay-Angriffe
- doppelte Confirms
- fremde Confirm-Versuche

---

# Rate Limiting

Mehrere Rate-Limits sind aktiv.

Services:

- DeviceRateLimitService
- DeviceConfirmRateLimitService

Zweck:

- Schutz vor API-Missbrauch
- Schutz vor Flooding
- Stabilisierung des Systems

Empfehlung:

Rate-Limit-Events sollten überwacht werden.

Viele 429-Antworten können bedeuten:

- Fehlkonfiguration
- defektes Device
- Angriffsversuch

---

# Audit Logging

Alle Workflow-Schritte werden protokolliert.

Primäre Tabelle:

tl_co_door_log

Typische Log-Ereignisse:

- door.open
- device.poll
- door.dispatch
- door.confirm
- door.expired

Jeder Workflow enthält:

correlationId

Diese ermöglicht:

vollständige Ablaufrekonstruktion über:

open → poll → dispatch → confirm

---

# Ergänzende Sicherheitsmechanismen

Zusätzlich vorhanden:

- Workflow-State-Machine
- Statusprüfung vor Confirm
- Device-Bindung pro Dispatch
- Expiry-Mechanismus
- atomarer Dispatch

Diese Mechanismen verhindern:

- Race Conditions
- Mehrfach-Dispatch
- Mehrfach-Bestätigung

---

# Zeitabhängige Sicherheit

Das System ist zeitabhängig.

Relevant:

- confirmWindow
- job expiry
- Rate-Limit-Zeitfenster

Empfehlung:

- Server-Zeit synchronisieren (NTP)
- Device-Zeit synchronisieren

Zeitabweichungen können zu:

- Confirm-Fehlern
- falschen Expiry-Ereignissen
- inkonsistentem Verhalten

führen.

---

# Empfehlung für weitere Sicherheitsprüfungen

Optional sinnvoll:

## Token Rotation

Empfohlen:

alle 3–6 Monate

## Monitoring

Überwachen:

- 403-Fehler
- 410 expired
- 429 Rate-Limits

## Netzwerksegmentierung

Empfohlen:

- VLAN oder Gast-LAN für Devices
- Trennung vom internen Netzwerk

---

# Zusammenfassung

Die aktuelle Architektur reduziert typische Risiken durch:

- Pull-Modell statt Push
- Nonce-basierte Bestätigung
- Device-Bindung
- Rate-Limits
- Logging mit Correlation-ID

Restrisiken bestehen weiterhin bei:

- kompromittierten Device-Secrets
- kompromittierten Benutzerkonten
- physischem Zugriff auf Hardware

Diese Risiken sind systemtypisch
und können nicht vollständig eliminiert werden.
