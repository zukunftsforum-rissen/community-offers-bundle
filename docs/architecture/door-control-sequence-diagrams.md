# Door Control Sequence Diagrams
Community Offers Bundle – Door Control Runtime Sequences

Dieses Dokument beschreibt den exakten Ablauf der Türöffnung
für alle drei Betriebsmodi:

- live
- emulation
- simulation

Die Diagramme sind in **PlantUML** dargestellt.

---

# Live Mode

Produktiver Betrieb mit realer Hardware.

Eigenschaften:

- Job wird erzeugt
- Raspberry Pi pollt
- Job wird dispatcht
- Confirm wird gesendet
- Tür öffnet physisch

## Sequence

```plantuml
@startuml
actor User
participant App
participant OpenDoorService
participant DoorJobService
participant Database
participant RaspberryPi
participant DeviceAPI

User -> App : Tür öffnen
App -> OpenDoorService : open(area)

OpenDoorService -> DoorJobService : createJob()
DoorJobService -> Database : INSERT door_job
Database --> DoorJobService : jobId

RaspberryPi -> DeviceAPI : poll(deviceId)
DeviceAPI -> DoorJobService : fetchPendingJob()

DoorJobService --> DeviceAPI : job
DeviceAPI --> RaspberryPi : dispatch

RaspberryPi -> DeviceAPI : confirm(jobId)
DeviceAPI -> DoorJobService : confirmExecution()

DoorJobService -> Database : update executedAt
@enduml
```

---

# Emulation Mode

Workflow-Testmodus ohne reale Hardware.

Eigenschaften:

- identischer Ablauf wie live
- Emulator pollt statt Raspberry Pi
- keine physische Türöffnung

## Sequence

```plantuml
@startuml
actor User
participant App
participant OpenDoorService
participant DoorJobService
participant Database
participant EmulatorDevice
participant DeviceAPI

User -> App : Tür öffnen
App -> OpenDoorService : open(area)

OpenDoorService -> DoorJobService : createJob()
DoorJobService -> Database : INSERT door_job
Database --> DoorJobService : jobId

EmulatorDevice -> DeviceAPI : poll(deviceId)
DeviceAPI -> DoorJobService : fetchPendingJob()

DoorJobService --> DeviceAPI : job
DeviceAPI --> EmulatorDevice : dispatch

EmulatorDevice -> DeviceAPI : confirm(jobId)
DeviceAPI -> DoorJobService : confirmExecution()

DoorJobService -> Database : update executedAt
@enduml
```

---

# Simulation Mode

Demo-Modus ohne Workflow.

Eigenschaften:

- kein Job
- kein Polling
- kein Confirm
- direkter Erfolg

## Sequence

```plantuml
@startuml
actor User
participant App
participant OpenDoorService
participant SimulatorDoorGateway

User -> App : Tür öffnen
App -> OpenDoorService : open(area)

OpenDoorService -> SimulatorDoorGateway : simulateOpen()

SimulatorDoorGateway --> OpenDoorService : success

OpenDoorService --> App : simulated success
@enduml
```

---

# Architekturübersicht

Die drei Modi unterscheiden sich hauptsächlich im **Workflow-Verhalten**.

| Mode | Job | Polling | Confirm | Device |
|-----|-----|--------|--------|-------|
| live | ja | ja | ja | Raspberry Pi |
| emulation | ja | ja | ja | Emulator |
| simulation | nein | nein | nein | keiner |

---

# Channel

Zusätzlich wird der Ausführungspfad gespeichert.

| Channel | Beschreibung |
|-------|-------------|
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
