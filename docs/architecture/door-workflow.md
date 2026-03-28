# Door Workflow

This document describes the lifecycle of a door access request in the
Community Offers Bundle.

The workflow coordinates interactions between:

- members
- the Contao backend
- the door job queue
- devices (Raspberry Pi or Emulator)
- audit logging

---

# Overview

The door access workflow follows a **job-based pull model**.

A member request creates a door job which is later executed by a device
that polls the server.

Workflow:

member open request
        ↓
gateway resolved
        ↓
door job created (pending)
        ↓
device poll
        ↓
job dispatched
        ↓
device confirm
        ↓
executed / failed / expired

---

# Step 1 – Member Request

Endpoint:

POST /api/door/open/{area}

The backend:

1. verifies member authentication
2. checks permission for the requested area
3. resolves the correct gateway
4. creates a new **door job**

Important fields created:

- correlationId
- area
- nonce
- requestedByMemberId
- createdAt
- expiresAt

Status:

pending

---

# Step 2 – Device Poll

Endpoint:

POST /api/device/poll

The device periodically polls the backend.

The backend:

1. authenticates the device user
2. determines the deviceId
3. determines device areas
4. searches for pending jobs matching the device areas

If a job exists:

- status changes to **dispatched**
- a dispatch response is returned

Dispatch response typically includes:

- jobId
- area
- nonce

---

# Step 3 – Door Execution

The device performs the physical action.

Typical hardware chain:

Raspberry Pi  
→ GPIO / HTTP  
→ Shelly / relay  
→ electric door strike  

In emulator mode:

Emulator  
→ simulated execution  

---

# Step 4 – Device Confirm

Endpoint:

POST /api/device/confirm

Payload example:

{
  "jobId": "...",
  "nonce": "...",
  "result": "success"
}

The backend verifies:

- jobId exists
- nonce matches
- job is currently dispatched

If valid:

status → executed

If the device reports an error:

status → failed

---

# Step 5 – Expiration

Jobs that are not confirmed within the configured
`confirm_window` may transition to:

expired

This prevents stale jobs from executing later.

---

# State Machine

Possible job states:

pending  
dispatched  
executed  
failed  
expired  

State transitions:

pending → dispatched  
dispatched → executed  
dispatched → failed  
pending → expired  
dispatched → expired  

---

# Audit Logging

Every workflow step creates an entry in:

tl_co_door_log

Typical actions:

door_open  
door_dispatch  
door_confirm  

Each entry contains:

- correlationId
- deviceId
- memberId
- area
- action
- result

The correlationId allows reconstruction of the full workflow.

---

# Monitoring

Device monitoring derives information from workflow events.

Typical indicators:

- lastPollAt
- lastConfirmAt
- lastArea
- device status

Emulator devices can optionally be excluded from
production monitoring views.

---

# Security Considerations

Key security mechanisms:

- Device authentication
- Nonce validation
- Rate limiting
- Audit logging
- Correlation ID tracing

These ensure that door access cannot be replayed or forged.
