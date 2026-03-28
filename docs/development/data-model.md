# Data Model

Dieses Dokument beschreibt die zentralen Datenstrukturen
des Türsteuerungssystems.

---

# tl_co_door_job

Repräsentiert einen Tür-Job, der erstellt wird,
wenn ein Mitglied eine Tür öffnen möchte.

Typische Felder:

- id
- area
- requestedByMemberId
- correlationId (optional, primär in tl_co_door_log gespeichert)
- requestIp (optional, ggf. anonymisiert)
- userAgent (optional, ggf. gekürzt)
- nonce
- dispatchToDeviceId
- status
- createdAt
- expiresAt
- dispatchedAt
- executedAt
- attempts
- resultCode
- resultMessage

Optionale Millisekunden-Felder
(nur wenn benötigt):

- createdAtMs
- dispatchedAtMs
- executedAtMs

Hinweis:

Millisekundenfelder sind nur erforderlich,
wenn hochauflösende Zeitmessung benötigt wird.

---

## Status-Werte

Typische Statuswerte:

- pending
- dispatched
- executed
- failed
- expired

Wichtig:

Der Status:

expired

ersetzt ältere Begriffe wie:

timeout

---

# tl_co_door_log

Audit-Log für alle Workflow-Ereignisse.

Diese Tabelle ist die **primäre Quelle**
für Ablauf-Nachverfolgung.

Wichtige Felder:

- id
- tstamp
- correlationId
- deviceId
- memberId
- area
- action
- result
- ip (optional, ggf. anonymisiert)
- userAgent (optional)
- message
- context

---

## Actions (Beispiele)

Typische Aktionen:

- door_open
- door_dispatch
- door_confirm
- door_expired
- device_poll
- request_access

---

## Results (Beispiele)

Typische Result-Werte:

- granted
- forbidden
- rate_limited
- dispatched
- executed
- failed
- expired

Nicht empfohlen:

timeout

---

# Datenschutz-Hinweise

Felder mit potenziell personenbezogenen Daten:

- requestIp
- userAgent
- memberId

Empfehlungen:

- IP-Adressen anonymisieren
- User-Agent ggf. kürzen
- Logging auf notwendige Daten beschränken

---

# Modellierungsprinzipien

Das Datenmodell folgt folgenden Prinzipien:

## Nachvollziehbarkeit

Alle Workflow-Schritte sind über:

correlationId

verknüpft.

Primäre Nutzung:

- tl_co_door_log

---

## Zustandskonsistenz

Statusübergänge erfolgen nur
in definierter Reihenfolge:

pending  
→ dispatched  
→ executed / failed / expired  

---

## Idempotenz

Ein Job darf:

- nicht mehrfach erfolgreich bestätigt werden
- nicht mehrfach dispatcht werden

Diese Regeln werden
durch Statusprüfung
und atomare Datenbankoperationen
durchgesetzt.
