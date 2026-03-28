
# Runtime Modes & Execution Channels
Community Offers Bundle – Door Control Architecture

## Ziel
Das System unterstützt zwei Betriebsmodi, um unterschiedliche Anforderungen zu erfüllen:

1. Produktivbetrieb (live)
2. Diagnose/Testbetrieb mit vollem Workflow (emulator)

Diese Modi ermöglichen:

- sicheren Produktivbetrieb
- reproduzierbare Fehleranalyse in Produktion

Die Modi werden über die Konfiguration `community_offers.mode` gesteuert.

---

# Betriebsmodi (Mode)

## 1. live

Produktiver Betrieb mit realer Hardware.

Eigenschaften:

- Türöffnung erfolgt physisch
- Raspberry Pi pollt die Device API
- vollständiger Workflow
- echte Gerätekommunikation

Workflow:

App  
↓  
OpenDoorService  
↓  
DoorJobService  
↓  
Job in DB  
↓  
Raspberry Pi pollt  
↓  
Dispatch  
↓  
Confirm  
↓  
Workflow abgeschlossen  

Erlaubte Devices:

isEmulator = false

Verboten:

isEmulator = true

---

## 2. emulator

Diagnose- und Testmodus mit vollständigem Workflow, aber ohne reale Hardware.

Zweck:

- Fehleranalyse in Produktion
- Nachstellen von Abläufen
- Workflow-Debugging

Eigenschaften:

- identischer Ablauf wie im Live-Modus
- Job wird erzeugt
- Polling und Confirm laufen
- keine physische Türöffnung

Workflow:

App  
↓  
OpenDoorService  
↓  
DoorJobService  
↓  
Job in DB  
↓  
Emulator Device pollt  
↓  
Dispatch  
↓  
Confirm (Emulator)  
↓  
Workflow abgeschlossen  

Erlaubte Devices:

isEmulator = true

Verboten:

isEmulator = false

---

# Device-Typen

Devices besitzen ein Merkmal:

isEmulator (bool)

Bedeutung:

| Device | Beschreibung |
|------|-------------|
| isEmulator = false | reales Device (Raspberry Pi) |
| isEmulator = true | Emulator-Device für Workflow-Tests |

---

# Execution Channel

Zusätzlich zum Mode wird der technische Ausführungspfad gespeichert.

## channel

physical  
emulator  

Bedeutung:

| Channel | Beschreibung |
|-------|-------------|
| physical | reale Hardware |
| emulator | Emulator-Device |

Der Channel wird beim Dispatch anhand des verwendeten Gateways gesetzt.

---

# Gateway Selection

Der konkrete Ausführungspfad wird über den DoorGatewayResolver bestimmt.

Mapping:

mode = live  
→ RaspberryDoorGateway  
→ channel = physical  

mode = emulator  
→ EmulatorDoorGateway  
→ channel = emulator

---

# Datenmodell

## Door Job Tabelle

Neue Felder:

mode  
channel  

### Beispiel

Live-Job:

mode = live  
channel = physical  

emulator:

mode = emulator
channel = emulator  

Erzeugt einen normalen DoorJob, aber ohne physische Türöffnung.
---

# Logging

Alle Door-Logs enthalten zusätzlich:

mode  
channel  

Beispiel:

door_open.success

context:
  area: workshop
  memberId: 23
  mode: emulator
  channel: emulator
  jobId: 1284

Damit lassen sich später eindeutig unterscheiden:

- echte Öffnungen
- emulator
