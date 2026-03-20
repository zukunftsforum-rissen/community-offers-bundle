docs/architecture/device-policy.md
# Device Policy
Community Offers Bundle – Device Participation Rules

## Ziel

Dieses Dokument definiert, welche Devices in welchem Betriebsmodus
(`community_offers.mode`) am Door-Workflow teilnehmen dürfen.

Ziel ist es, sicherzustellen, dass:

- reale Hardware nur im Live-Modus aktiv ist
- Emulatoren niemals reale Türen öffnen können
- Tests und Fehleranalysen in Produktion sicher möglich sind

---

# Device Types

Devices besitzen ein Merkmal:


isEmulator (bool)


Bedeutung:

| Device | Beschreibung |
|------|-------------|
| isEmulator = false | reales Device (Raspberry Pi) |
| isEmulator = true | Emulator-Device für Workflow-Tests |

---

# Betriebsmodi

Das System kennt zwei Betriebsmodi:


live
emulator


---

# Device Participation Matrix

| Mode | Poll erlaubt | Confirm erlaubt | Device Typ |
|-----|-------------|----------------|------------|
| live | ja | ja | isEmulator = false |
| emulator | ja | ja | isEmulator = true |

---

# Sicherheitsregeln

## Live-Modus

Im Live-Modus dürfen nur reale Geräte teilnehmen.

Regel:


if ($mode === 'live' && $device->isEmulator()) {
deny();
}


Ziel:

- Emulator kann niemals reale Tür öffnen
- Produktionsbetrieb bleibt geschützt

---

## Emulator-Modus

Im emulator-Modus dürfen ausschließlich Emulator-Devices teilnehmen.

Regel:


if ($mode === 'emulator' && !$device->isEmulator()) {
deny();
}


Ziel:

- reale Hardware wird nicht ausgelöst
- vollständiger Workflow kann trotzdem getestet werden

---

# Device Endpoints

Die Regeln gelten für folgende API-Endpunkte:


/api/device/poll
/api/device/confirm
/api/device/heartbeat


Diese Endpunkte müssen jeweils prüfen:

- aktuellen Systemmodus
- Device-Typ (`isEmulator`)

---

# Vorteile dieser Architektur

Die klare Trennung ermöglicht:

- sicheren Produktivbetrieb
- vollständige Workflow-Tests ohne Hardware
- reproduzierbare Fehlersituationen in Produktion
- klare Analyse im Logging
