
# Runtime Modes & Execution Channels
Community Offers Bundle – Door Control Architecture

## Ziel
Das System unterstützt drei Betriebsmodi, um unterschiedliche Anforderungen zu erfüllen:

1. Produktivbetrieb (live)
2. Diagnose/Testbetrieb mit vollem Workflow (emulation)
3. Präsentations-/Demo-Modus ohne Workflow (simulation)

Diese Modi ermöglichen:

- sicheren Produktivbetrieb
- reproduzierbare Fehleranalyse in Produktion
- Hardware-freie Demonstrationen

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

## 2. emulation

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

## 3. simulation

Demo- und Präsentationsmodus ohne Workflow.

Zweck:

- Präsentationen
- UI-Demonstrationen
- Hardwarefreie Tests

Eigenschaften:

- kein Job
- kein Polling
- kein Confirm
- direkter Erfolg

Workflow:

App  
↓  
OpenDoorService  
↓  
SimulatorDoorGateway  
↓  
Direkter Erfolg  

Es existiert kein Device-Workflow.

---

# Device-Typen

Devices besitzen ein Merkmal:

isEmulator (bool)

Bedeutung:

| Device | Beschreibung |
|------|-------------|
| isEmulator = false | reales Device (Raspberry Pi) |
| isEmulator = true | Emulator-Device für Workflow-Tests |

Ein separates `isSimulator` ist nicht mehr erforderlich.

---

# Execution Channel

Zusätzlich zum Mode wird der technische Ausführungspfad gespeichert.

## channel

physical  
emulator  
simulator  

Bedeutung:

| Channel | Beschreibung |
|-------|-------------|
| physical | reale Hardware |
| emulator | Emulator-Device |
| simulator | direkte Simulation |

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

Emulation:

mode = emulation  
channel = emulator  

Simulation erzeugt keinen Job.

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
  mode: emulation
  channel: emulator
  jobId: 1284

Damit lassen sich später eindeutig unterscheiden:

- echte Öffnungen
- Emulation
- Demo-Simulation
