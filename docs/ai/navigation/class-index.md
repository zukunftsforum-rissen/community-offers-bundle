# Class Index (AI Navigation Aid)

This file lists the most important classes used in the Community Offers Bundle.
It helps AI tools and developers quickly identify core logic locations.

⚠️ This file must stay aligned with the actual codebase.

---

# Core Controllers

AccessController  
Primary member entry point.

Handles:

POST /api/door/open/{area}

DeviceController  
Primary device interaction endpoint.

Handles:

POST /api/device/poll  
POST /api/device/confirm  
GET /api/device/whoami

---

# Core Services

OpenDoorService  
Main orchestration service for door requests.

DoorJobService  
Implements workflow logic and state transitions.

DoorAuditLogger  
Writes structured workflow audit logs.

DoorGatewayResolver  
Selects correct gateway implementation based on runtime mode.

SystemMode  
Provides runtime mode information (live / emulator).

AccessService  
Handles mapping between areas and member permissions.

AccessRequestService  
Processes access request workflows.

DeviceAuthService  
Authenticates devices via API tokens.

DeviceHeartbeatService  
Tracks device activity and online status.

DeviceMonitorService  
Builds device status information for monitoring UI.

---

# Gateway Implementations

RaspberryDoorGateway  
Hardware-based gateway for live mode.

EmulatorDoorGateway  
Software-based gateway used in emulator mode.

---

# Notes   

If classes listed here do not exist,
or new important services are added,
this file must be updated.

This file is intended as:

AI navigation support  
not architectural documentation.

