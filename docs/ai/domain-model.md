# Domain Model

This document describes the core domain entities
used by the Community Offers system.

The domain model reflects the logical structure
of the system and must stay consistent with:

- database schema
- workflow states
- service logic

---

# Core Entities

## DoorJob

Represents a single request to open a door.

Created when:

POST /api/door/open/{area}

is executed.

---

### DoorJob Lifecycle

The full lifecycle includes:

pending → dispatched → executed  
pending → expired  
dispatched → failed  
dispatched → expired

Important:

expired may occur:

- before dispatch (timeout)
- after dispatch (late confirm)

Terminal states:

- executed
- failed
- expired

---

### DoorJob Key Attributes

Typical attributes include:

- id
- area
- status
- nonce
- createdAt
- expiresAt
- requestedByMemberId
- dispatchToDeviceId
- attempts
- resultCode
- resultMessage

Optional but important:

- correlationId (logging context)

These attributes define:

- execution flow
- validation behavior
- retry logic

---

## Device

Represents a physical Raspberry Pi device
responsible for door execution.

Devices:

- authenticate via API token
- poll server periodically
- receive dispatch instructions
- execute door hardware action
- confirm execution result

---

### Device Characteristics

Devices:

- never receive inbound connections
- use pull-based communication
- poll at fixed intervals (~2 seconds)
- manage hardware execution locally

Device identity:

deviceId

is derived from:

authentication token

not from request parameters.

---

## DoorLog

Represents an audit log entry.

Stored in:

tl_co_door_log

Purpose:

- trace system activity
- debug failures
- support auditing
- monitor behavior

---

### DoorLog Attributes

Typical attributes:

- id
- tstamp
- action
- result
- area
- memberId
- message
- context

Optional attributes:

- correlationId
- deviceId

These logs enable:

- workflow tracing
- failure diagnostics
- security auditing

---

# Supporting Concepts

## Nonce

A nonce is:

a unique token

generated when:

a job is dispatched.

Purpose:

- prevent replay attacks
- ensure correct confirmation
- validate execution integrity

Nonce must:

- match dispatched value
- be confirmed exactly once

---

## Correlation ID

Correlation IDs are used to:

- connect related log entries
- trace workflow execution
- debug production issues

Typical lifecycle:

open request  
→ job created  
→ dispatch logged  
→ confirm logged

All share:

same correlationId

---

## Confirm Window

Defines:

maximum time allowed

between:

dispatch  
and  
confirm

If confirmation occurs after:

confirmWindow

Job status becomes:

expired

---

# Domain Relationships

Logical relationships:

Member → DoorJob  
Device → DoorJob  
Member → DoorLog  
AccessRequest → Member

Important:

Some relationships are:

logical

not direct foreign keys.

---

# Domain Integrity Rules

These rules must always hold:

- DoorJob status transitions are valid
- Nonce values are unique per job
- Device identity is authenticated
- Expired jobs cannot be executed
- Confirm operations are idempotent

Violation of these rules may cause:

- inconsistent workflow state
- incorrect door execution
- security risks

---

# Summary

The domain model defines:

- how entities interact
- how workflows operate
- how system integrity is preserved

Any change to:

- workflow logic
- job lifecycle
- device behavior

must be reflected in:

this domain model documentation.

