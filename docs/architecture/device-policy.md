# Device Policy
Community Offers Bundle – Device Participation Rules

Dieses Dokument definiert, welche Devices in welchem Betriebsmodus
(`community_offers.mode`) am Door-Workflow teilnehmen dürfen.

Diese Regeln sind sicherheitskritisch.

---

# Ziel

Die Device Policy stellt sicher:

- reale Hardware wird nur im Live-Modus verwendet
- Emulatoren können niemals reale Türen öffnen
- Fehlkonfigurationen bleiben ungefährlich
- Workflow-Tests sind sicher möglich

---

# Device Types

Devices besitzen ein zentrales Merkmal:

isEmulator (bool)

Bedeutung:

| Device-Typ | Beschreibung |
|------------|--------------|
| isEmulator = false | reales Device (z. B. Raspberry Pi) |
| isEmulator = true  | Emulator-Device |

Dieses Flag wird beim Authentifizieren eines Devices geprüft.

---

# Betriebsmodi

Das System kennt aktuell zwei Betriebsmodi:

live
emulator

Der aktive Modus wird konfiguriert über:

community_offers.mode

---

# Device Participation Matrix

Diese Matrix beschreibt, welche Devices teilnehmen dürfen.

| Mode     | Poll erlaubt | Confirm erlaubt | Erlaubter Device-Typ |
|----------|--------------|-----------------|----------------------|
| live     | ja           | ja              | isEmulator = false   |
| emulator | ja           | ja              | isEmulator = true    |

Alle anderen Kombinationen sind verboten.

---

# Sicherheitsregeln

## Regel 1: Live-Modus schützt reale Hardware

Im Live-Modus dürfen ausschließlich reale Devices teilnehmen.

Pseudo-Code:

```php
if ($mode === 'live' && $device->isEmulator()) {
    deny();
}
```

Ziel:

- Emulator darf niemals reale Hardware steuern
- Produktionsbetrieb bleibt geschützt

---

## Regel 2: Emulator-Modus schützt reale Hardware

Im Emulator-Modus dürfen ausschließlich Emulator-Devices teilnehmen.

Pseudo-Code:

```php
if ($mode === 'emulator' && !$device->isEmulator()) {
    deny();
}
```

Ziel:

- reale Hardware bleibt vollständig deaktiviert
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
- Teilnahmeberechtigung

Diese Prüfung erfolgt typischerweise in:

DeviceAuthService
DeviceAccessPolicy

---

# Verhalten bei Regelverletzung

Wenn ein Device nicht teilnehmen darf:

Typische Maßnahmen:

- Zugriff verweigern
- HTTP Fehler zurückgeben
- Event loggen

Beispiel:

HTTP 403 Forbidden

Optional:

- Audit-Log schreiben
- Diagnoseinformationen speichern

---

# Sicherheitsprinzipien

Die Device Policy basiert auf folgenden Prinzipien.

## Prinzip 1: Fail Safe Default

Wenn ein Zustand unklar ist:

deny access

Nicht:

allow access

---

## Prinzip 2: Mode-Isolation

Modes dürfen sich gegenseitig nicht beeinflussen.

Beispiele:

Live-Mode:
keine Emulator-Devices

Emulator-Mode:
keine realen Devices

---

## Prinzip 3: Server-seitige Kontrolle

Alle Prüfungen müssen serverseitig erfolgen.

Nicht:

- im Frontend
- im Device
- im Emulator

Nur:

Server entscheidet

---

# Typische Fehlerfälle

## Falscher Device-Typ

Beispiel:

mode = live
device.isEmulator = true

Erwartetes Verhalten:

deny access

---

## Falscher Mode

Beispiel:

mode falsch gesetzt

Erwartetes Verhalten:

keine Teilnahme erlauben

---

## Device deaktiviert

Wenn:

enabled = false

Dann:

deny access

---

# Logging und Audit

Bei Policy-Verletzungen sollten folgende Daten geloggt werden:

- deviceId
- mode
- isEmulator
- timestamp
- reason

Beispiel:

device_denied

Dies unterstützt:

- Diagnose
- Sicherheit
- Nachvollziehbarkeit

---

# Zusammenhang mit Workflow

Die Device Policy beeinflusst:

/api/device/poll
/api/device/confirm

Wenn ein Device blockiert wird:

- kein Job wird ausgeliefert
- kein Confirm akzeptiert
- Workflow bleibt geschützt

---

# Zusammenfassung

Die Device Policy stellt sicher:

- reale Hardware bleibt geschützt
- Emulator bleibt isoliert
- Fehlkonfigurationen sind ungefährlich
- Workflow bleibt kontrollierbar

Diese Regeln sind zentral für:

- Sicherheit
- Stabilität
- Wartbarkeit
- Testbarkeit
