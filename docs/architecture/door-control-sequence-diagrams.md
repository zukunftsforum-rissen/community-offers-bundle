# Door Control Sequence Diagrams
Community Offers Bundle – Door Control Runtime Sequences

Dieses Dokument beschreibt den exakten Ablauf der Türöffnung
für alle unterstützten Betriebsmodi:

- live
- emulator

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
participant DoorGatewayResolver
participant RaspberryDoorGateway
participant DoorJobService
participant Database
participant RaspberryPi
participant DeviceAPI

User -> App : Tür öffnen
App -> OpenDoorService : open(area)

OpenDoorService -> DoorGatewayResolver : resolve(mode=live)
DoorGatewayResolver -> RaspberryDoorGateway : gateway

RaspberryDoorGateway -> DoorJobService : createJob()
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

# Emulator Mode

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
participant DoorGatewayResolver
participant EmulatorDoorGateway
participant DoorJobService
participant Database
participant EmulatorDevice
participant DeviceAPI

User -> App : Tür öffnen
App -> OpenDoorService : open(area)

OpenDoorService -> DoorGatewayResolver : resolve(mode=emulator)
DoorGatewayResolver -> EmulatorDoorGateway : gateway

EmulatorDoorGateway -> DoorJobService : createJob()
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

# Architekturhinweis

Die beiden Modi unterscheiden sich ausschließlich im verwendeten Gateway
und im Device-Typ.

| Mode     | Gateway                | Device Type        |
|----------|------------------------|--------------------|
| live     | RaspberryDoorGateway   | Raspberry Pi       |
| emulator | EmulatorDoorGateway    | Emulator Device    |

---

# Channel

Zusätzlich wird der Ausführungspfad gespeichert.

| Channel   | Beschreibung        |
|-----------|--------------------|
| physical  | reale Hardware      |
| emulator  | Emulator-Device     |

Zuordnung:

live → physical  
emulator → emulator  

---

# Datenmodell

DoorJobs speichern mindestens:

mode  
channel  
area  
correlationId  
expiresAt  

Beispiele:

Live:

mode = live  
channel = physical  

Emulator:

mode = emulator  
channel = emulator  
