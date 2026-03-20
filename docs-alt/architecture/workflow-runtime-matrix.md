# Workflow Runtime Matrix
Community Offers Bundle – Runtime Behaviour Overview

Dieses Dokument zeigt das Laufzeitverhalten der Door-Control-Architektur
für alle Betriebsmodi.

---

# Betriebsmodi

Das System kennt zwei Modi:


live
emulator


---

# Workflow Matrix

| Mode | Job erstellt | Polling | Confirm | Device Typ | Channel |
|-----|-------------|--------|--------|------------|--------|
| live | ja | ja | ja | physical | physical |
| emulator | ja | ja | ja | emulator | emulator |

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

# Mode: emulator

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

# Channel

Der Channel beschreibt den technischen Ausführungsweg.

| Channel | Bedeutung |
|-------|-----------|
| physical | reale Hardware |
| emulator | Emulator-Device |

---

# Datenmodell

Jobs speichern:


mode
channel


Beispiele:

Live:


mode = live
channel = physical


Emulator:


mode = emulator
channel = emulator
