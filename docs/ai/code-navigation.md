# Code Navigation Guide (AI)

This document helps an AI quickly locate the most important parts
of the Community Offers Bundle codebase.

Goal:

Fast orientation and correct entry points.

Important:

The codebase itself is always the canonical source of truth.

This file only defines navigation hints.

---

# Recommended Reading Order

When analysing the project, start with:

1. AccessController
2. DeviceController
3. OpenDoorService
4. DoorJobService
5. DoorAuditLogger
6. SystemMode
7. DoorGatewayResolver
8. Relevant workflow tests

Important correction:

There is no DoorController class.

Member requests are handled by:

AccessController

---

# Controllers

## AccessController

Handles member door requests.

Important endpoint:

POST /api/door/open/{area}

Responsibilities:

- verify member authentication
- verify access rights
- call OpenDoorService
- return jobId
- handle rate limiting

This is the primary entry point
for member-triggered workflows.

---

## DeviceController

Handles device-side interactions.

Typical endpoints:

- POST /api/device/poll
- POST /api/device/confirm
- GET /api/device/whoami

Responsibilities:

- authenticate device
- dispatch jobs
- validate nonce
- confirm execution result
- update heartbeat

---

# Core Services

## OpenDoorService

Primary orchestration service
for member-triggered door actions.

Responsibilities:

- verify permissions
- create DoorJob
- select DoorGateway
- trigger workflow logic

Important:

This is the orchestration layer
between controller and workflow.

---

## DoorJobService

Central workflow state service.

Responsibilities:

- create jobs
- dispatch jobs
- confirm jobs
- expire jobs
- enforce state transitions

Important:

This service represents the
workflow state machine.

---

## DoorGatewayResolver

Selects the correct gateway
based on runtime mode.

Responsibilities:

- resolve live vs emulator gateway
- enforce single gateway match
- provide gateway instance

---

## SystemMode

Defines runtime mode.

Typical modes:

- live
- emulator

Responsibilities:

- expose runtime mode
- enable mode-aware logic

---

## DoorAuditLogger

Writes audit events to:

tl_co_door_log

Typical events:

- door_open
- door_dispatch
- door_confirm
- door_failed
- door_expired

Important:

Audit logging is critical
for debugging workflows.

---

## DeviceMonitorService

Builds device status information
from logs and heartbeat timestamps.

Used for:

- device monitor UI
- online/offline detection

---

## DeviceHeartbeatService

Tracks device activity.

Triggered by:

device poll events.

Used to determine:

device availability.

---

# Database / DCA

Important tables:

## tl_co_door_job

Represents queued door jobs.

Key concepts:

- job lifecycle
- dispatch
- nonce validation
- expiration

---

## tl_co_door_log

Audit log for all workflow events.

Used for:

- observability
- debugging
- monitoring

---

## tl_co_device

Stores registered devices.

Key fields:

- deviceId
- areas
- apiTokenHash
- enabled
- isEmulator
- lastSeen

---

# Tests

Tests are one of the best sources
for understanding real behaviour.

Important tests:

- DeviceControllerTest
- DoorWorkflowCorrelationTest
- DoorJobServiceTest
- AccessServiceTest

They describe:

- expected API behaviour
- workflow lifecycle
- error handling

---

# Workflow Entry Points

Most workflows begin in:

Member flow:

AccessController
→ OpenDoorService
→ DoorJobService

Device flow:

DeviceController
→ DoorJobService

Logging:

DoorAuditLogger

---

# Debugging Workflow

Typical debugging order:

1. Inspect tl_co_door_log
2. Find correlationId
3. Inspect DoorJobService state
4. Inspect DeviceController
5. Review workflow tests

Important:

Logs are usually the fastest
source of truth.

---

# Key Concept Summary

DoorJob

Queued door action.

Dispatch

Assigning job to device.

Confirm

Device reports execution result.

CorrelationId

Trace identifier across logs.

Device

Hardware controller
executing door actions.

