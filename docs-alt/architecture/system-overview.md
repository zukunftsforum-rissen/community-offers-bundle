# System Architecture

The system follows a **poll‑based device architecture**.

Devices (Raspberry Pi controllers) poll the server for pending jobs.
No inbound connections to the device network are required.

## Core Services

DoorJobService
DoorAuditLogger
DoorWorkflowTimelineService
DeviceHeartbeatService
DeviceMonitorService
SimulatorDeviceService
CorrelationIdService
DeviceRateLimitService
DeviceConfirmRateLimitService

## Observability

Each workflow receives a **Correlation ID** which is written to:

- API logs
- tl_co_door_log
- workflow timeline