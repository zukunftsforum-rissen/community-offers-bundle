# Workflow Runtime Matrix
Community Offers Bundle – Runtime Behaviour Overview

Dieses Dokument beschreibt das Laufzeitverhalten des Door-Control-Workflows
für alle unterstützten Betriebsmodi.

Aktuell unterstützte Modi:

- live
- emulator

---

# Überblick

Die Runtime-Matrix zeigt:

- ob ein DoorJob erzeugt wird
- ob Polling stattfindet
- ob Confirm verwendet wird
- welche Device-Typen teilnehmen dürfen
- welcher Channel verwendet wird

---

# Workflow Matrix

| Mode     | Job erstellt | Polling | Confirm | Device Typ      | Channel   |
|----------|--------------|---------|---------|-----------------|-----------|
| live     | ja           | ja      | ja      | physical device | physical  |
| emulator | ja           | ja      | ja      | emulator device | emulator  |

---

# Mode: live

Produktiver Betrieb mit realer Hardware.

Eigenschaften:

- echte physische Türöffnung
- vollständiger Workflow
- reale Devices poll(en) und confirm(en)
- Emulator-Devices sind ausgeschlossen

Typischer Ablauf:

App
↓
OpenDoorService
↓
RaspberryDoorGateway
↓
DoorJobService
↓
Job in Datenbank
↓
Raspberry Pi pollt
↓
Dispatch
↓
Confirm
↓
Status: executed

---

# Mode: emulator

Workflow-Testmodus ohne reale Hardware.

Eigenschaften:

- identischer Workflow wie im Live-Modus
- Emulator-Device pollt
- keine physische Türöffnung
- reale Devices sind ausgeschlossen

Typischer Ablauf:

App
↓
OpenDoorService
↓
EmulatorDoorGateway
↓
DoorJobService
↓
Job in Datenbank
↓
Emulator pollt
↓
Dispatch
↓
Confirm
↓
Status: executed

---

# Polling-Verhalten

Polling erfolgt über:

/api/device/poll

Das Device:

- meldet sich regelmäßig
- fragt nach offenen Jobs
- erhält ggf. einen Job

Polling ist aktiv in:

live
emulator

---

# Confirm-Verhalten

Confirm erfolgt über:

/api/device/confirm

Confirm bedeutet:

- Job wurde erfolgreich ausgeführt
- Workflow wird abgeschlossen
- Status wird auf `executed` gesetzt

Confirm muss innerhalb eines Zeitfensters erfolgen.

Dieses Zeitfenster wird konfiguriert über:

community_offers.confirm_window

Wenn Confirm nicht rechtzeitig erfolgt:

Status → expired

---

# Channel

Der Channel beschreibt den technischen Ausführungspfad.

| Channel  | Bedeutung           |
|----------|---------------------|
| physical | reale Hardware      |
| emulator | Emulator-Ausführung |

Zuordnung:

live → physical
emulator → emulator

Der Channel wird beim Dispatch festgelegt.

---

# Device-Typ Verhalten

Devices besitzen:

isEmulator (bool)

Verhalten:

| Mode     | isEmulator=false | isEmulator=true |
|----------|------------------|-----------------|
| live     | erlaubt          | verboten        |
| emulator | verboten         | erlaubt         |

Diese Regeln müssen serverseitig geprüft werden.

---

# DoorJob Lifecycle im Betrieb

Ein Job durchläuft typischerweise:

pending
↓
dispatched
↓
executed

Oder:

pending
↓
expired

Oder:

pending
↓
dispatched
↓
expired

---

# Timeout-Verhalten

Ein Job läuft ab, wenn:

- kein Device pollt
- kein Confirm erfolgt
- Confirm zu spät erfolgt

Konfiguration:

community_offers.confirm_window

Typischer Wert:

30 Sekunden
(abhängig von Projektkonfiguration)

---

# Logging-Verhalten

Während der Laufzeit werden typischerweise folgende Events geloggt:

- door_open
- device_poll
- door_dispatch
- device_confirm
- door_expired

Diese Logs enthalten:

- jobId
- deviceId
- mode
- channel
- correlationId

Dadurch wird vollständige Nachverfolgbarkeit ermöglicht.

---

# Zusammenfassung

Das System arbeitet in beiden Modi mit identischem Workflow.

Unterschied:

live      → reale Hardware
emulator  → simulierte Hardware

Der Workflow selbst bleibt gleich.

Das erhöht:

- Testbarkeit
- Stabilität
- Diagnosefähigkeit
- Sicherheit
