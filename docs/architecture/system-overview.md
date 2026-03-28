# System Architecture Overview

This document provides a high-level overview of the
Community Offers system architecture.

The system follows a **poll-based device architecture**
with strict separation between application logic
and hardware interaction.

Devices poll the server for pending jobs.
No inbound connections to the device network are required.

---

# Architecture Model

Core architectural principles:

- Pull-based device communication
- No inbound network access to devices
- Job-based workflow execution
- Mode-based gateway resolution (live / emulator)
- Full audit logging with correlation IDs

---

# Core Services

The following services represent the central
functional building blocks of the system.

## Workflow & Job Handling

DoorJobService  
Responsible for:

- creating door jobs  
- managing job lifecycle  
- dispatching jobs to devices  
- processing confirmations  

DoorWorkflowTimelineService  
Responsible for:

- building workflow timelines  
- exposing state transitions  
- supporting diagnostics  

---

## Logging & Observability

DoorAuditLogger  
Responsible for:

- structured audit logging  
- recording workflow events  
- storing correlation-aware logs  

CorrelationIdService  
Responsible for:

- generating correlation IDs  
- maintaining traceability  
- linking workflow events  

---

## Device Interaction

DeviceHeartbeatService  
Responsible for:

- tracking device activity  
- updating lastSeen timestamps  

DeviceMonitorService  
Responsible for:

- monitoring device status  
- deriving online/offline states  

DeviceRateLimitService  
Responsible for:

- limiting device poll rates  

DeviceConfirmRateLimitService  
Responsible for:

- limiting confirm requests  

---

## Gateway Layer

DoorGatewayResolver  
Responsible for:

- resolving the correct gateway  
- selecting implementation by mode  

Available gateways:

- RaspberryDoorGateway (live mode)
- EmulatorDoorGateway (emulator mode)

---

# Removed / Legacy Components

The following component listed in earlier versions
is no longer part of the runtime:

DemoDeviceService  

This service has been removed as part of the
transition from demo-mode to emulator-mode.

---

# Observability

Each workflow receives a **Correlation ID**.

The correlation ID is written to:

- API logs  
- tl_co_door_log  
- workflow timeline  

This allows:

- full workflow tracing  
- debugging of device interactions  
- post-event diagnostics  

---

# Runtime Modes

The system currently supports:

- live mode
- emulator mode

These modes determine:

- which gateway implementation is used
- how device interaction behaves

Mode resolution is performed centrally
by the gateway resolver.

---

# Architectural Notes

Important design decisions:

- Hardware logic is encapsulated in gateways
- Services remain mode-agnostic
- Devices operate via polling
- All workflow events are auditable

This architecture supports:

- high reliability
- controlled hardware access
- simplified debugging
- safe production deployment
