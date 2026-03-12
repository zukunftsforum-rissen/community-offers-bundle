
# Code Navigation Guide (AI)

This document helps an AI quickly locate the most important parts of the
Community Offers Bundle codebase.

The goal is **fast orientation**: which files to read first and where the
main logic lives.

⚠️ The codebase is the canonical source of truth.
This document is a navigation aid.

---

# Recommended Reading Order

When analysing the project, start with:

1. DeviceController
2. DoorController
3. DoorJobService
4. DoorAuditLogger
5. DeviceMonitorService
6. Tests describing workflows

---

# Controllers

## DeviceController

Handles device interactions.

Typical endpoints:

- POST /api/device/poll
- POST /api/device/confirm
- GET /api/device/whoami

Responsibilities:

- authenticate device user
- dispatch door jobs
- validate nonce on confirm
- rate limiting

---

## DoorController

Handles member requests.

Important endpoint:

POST /api/door/open/{area}

Responsibilities:

- verify member permissions
- create DoorJob
- initiate workflow

---

# Core Services

## DoorJobService

Central service managing door jobs.

Typical responsibilities:

- create job
- dispatch job
- confirm job
- expire jobs

This service represents the **workflow state machine**.

---

## DoorAuditLogger

Writes audit events to:

tl_co_door_log

Typical actions:

- door_open
- door_dispatch
- door_confirm
- request_access

---

## DeviceMonitorService

Builds device status information from log events.

Used for:

- device monitor UI
- health tracking

Derives:

- lastPollAt
- lastConfirmAt
- device status

---

## DeviceHeartbeatService

Tracks device activity through poll events.

Used to determine online/offline state.

---

## SimulatorDeviceService

Used for the browser-based simulator.

Important:

Simulator endpoints differ from production device endpoints.

---

# Database / DCA

Important tables:

## tl_co_door_job

Represents queued door jobs.

Key concepts:

- job lifecycle
- dispatch to device
- nonce validation
- expiration

## tl_co_door_log

Audit log for all workflow events.

Used for:

- observability
- monitoring
- debugging

---

# Tests

Tests are one of the best sources for understanding behaviour.

Important tests:

DeviceControllerTest
DoorWorkflowCorrelationTest

They describe:

- expected API behaviour
- job lifecycle
- error handling

---

# Workflow Entry Points

Most workflows begin in one of these locations:

Member access request:

DoorController → DoorJobService

Device workflow:

DeviceController → DoorJobService

Logging:

DoorAuditLogger

---

# When Investigating a Problem

Typical debugging order:

1. Check tl_co_door_log
2. Locate correlationId
3. Inspect DoorJobService workflow
4. Inspect DeviceController poll/confirm handling
5. Review tests for expected behaviour

---

# Key Concept Summary

DoorJob
: queued door action

Dispatch
: assigning job to device

Confirm
: device confirms execution

CorrelationId
: trace identifier across logs

Device
: Raspberry Pi controlling door hardware
