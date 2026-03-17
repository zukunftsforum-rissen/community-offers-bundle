# Workflow Runtime Matrix
Community Offers Bundle – Runtime Behaviour Overview

Dieses Dokument zeigt das Laufzeitverhalten der Door-Control-Architektur
für alle Betriebsmodi.

---

# Betriebsmodi

Das System kennt drei Modi:


live
emulation
simulation


---

# Workflow Matrix

| Mode | Job erstellt | Polling | Confirm | Device Typ | Channel |
|-----|-------------|--------|--------|------------|--------|
| live | ja | ja | ja | physical | physical |
| emulation | ja | ja | ja | emulator | emulator |
| simulation | nein | nein | nein | – | simulator |

---

# Mode: live

Produktiver Betrieb.

Eigenschaften:

- echte Hardware
- vollständiger Workflow
- physische Türöffnung

Ablauf:

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

---

# Mode: emulation

Workflow-Testmodus ohne reale Hardware.

Eigenschaften:

- identischer Workflow wie live
- Emulator-Device pollt
- keine physische Türöffnung

Ablauf:

App  
↓  
OpenDoorService  
↓  
DoorJobService  
↓  
Job in DB  
↓  
Emulator pollt  
↓  
Dispatch  
↓  
Confirm  
↓  
Workflow abgeschlossen

---

# Mode: simulation

Demo- und Präsentationsmodus.

Eigenschaften:

- kein Workflow
- kein Job
- keine Device-Kommunikation
- direkter Erfolg

Ablauf:

App  
↓  
OpenDoorService  
↓  
SimulatorDoorGateway  
↓  
Direkter Erfolg

---

# Channel

Der Channel beschreibt den technischen Ausführungsweg.

| Channel | Bedeutung |
|-------|-----------|
| physical | reale Hardware |
| emulator | Emulator-Device |
| simulator | direkte Simulation |

---

# Datenmodell

Jobs speichern:


mode
channel


Beispiele:

Live:


mode = live
channel = physical


Emulation:


mode = emulation
channel = emulator


Simulation erzeugt keinen Job.
Empfohlene Ordnerstruktur
docs/
 └ architecture/
     runtime-modes.md
     device-policy.md
     workflow-runtime-matrix.md