# System Architecture (AI Overview)

This document provides a high-level architectural overview
of the Community Offers Bundle.

It is intended for:

- AI assistants
- Developers
- Review tools

---

# System Purpose

The Community Offers Bundle implements a:

Door Access Control System

for shared community spaces such as:

- workshop
- sharing room
- depot
- swap-house

The system ensures:

- controlled access
- reliable execution
- traceable operations
- secure device communication

---

# Core Components

The system consists of:

## 1) Contao Backend / Symfony Application

Responsible for:

- API endpoints
- Workflow execution
- Member access control
- Job creation and dispatch
- Audit logging
- Access request handling

Key Controllers:

- AccessController
- DeviceController
- AccessConfirmController

Key Services:

- OpenDoorService
- DoorJobService
- AccessRequestService
- AccessService
- DeviceHeartbeatService

---

## 2) Door Job Workflow

Central runtime mechanism.

Responsibilities:

- Manage door requests
- Track execution state
- Handle retries
- Handle expiration
- Enforce confirm window timing

Job lifecycle:

pending → dispatched → executed / failed / expired

---

## 3) Raspberry Pi Devices

Physical execution layer.

Responsibilities:

- Poll backend regularly
- Receive job dispatch
- Execute door action
- Confirm execution result

Characteristics:

- pull-based communication
- token authentication
- no inbound network connections
- fixed polling interval (~2 seconds)

---

## 4) Door Hardware Layer

Typically:

- Shelly relay
- Door actuator

Responsibilities:

- Execute physical door movement

---

## 5) Audit Logging System

All critical actions are recorded.

Examples:

- door_open
- device_poll
- door_dispatch
- door_confirm
- door_failed
- door_expired

Purpose:

- traceability
- debugging
- compliance
- incident analysis

---

# High-Level Runtime Flow

Typical execution sequence:

Member  
→ API (open request)  
→ DoorJob created  
→ Device poll  
→ Job dispatched  
→ Door executed  
→ Device confirm  
→ Job finalized

Important:

The server never pushes commands.

Devices always:

poll → receive → confirm

---

# Architectural Characteristics

The system follows these principles:

## Pull-Based Communication

Devices initiate all communication.

There are:

- no inbound device connections
- no push commands

This improves:

- network security
- firewall compatibility
- reliability

---

## Job-Based Execution Model

Door operations are handled as:

discrete jobs

Benefits:

- traceability
- retry capability
- failure recovery
- concurrency control

---

## Nonce-Based Confirmation

Each dispatched job includes:

nonce

This ensures:

- correct job confirmation
- replay protection
- state validation

---

## Time-Bound Execution

Each job has:

confirmWindow

If confirmation is delayed:

Job → expired

This prevents:

- stale operations
- uncontrolled retries

---

# System Constraints

The following constraints must always hold:

- Device polling interval is stable
- confirmWindow timing is enforced
- Authentication tokens are required
- No direct hardware exposure to public network
- Job state transitions are strictly controlled

Violation of these rules can lead to:

- inconsistent system state
- security risks
- hardware errors

---

# Failure Handling Model

Failures are expected and handled explicitly.

Possible outcomes:

- executed
- failed
- expired

Each outcome is:

- logged
- traceable
- recoverable

---

# Observability Model

The system includes:

- structured logging
- correlation IDs
- device monitoring
- job status tracking

This allows:

- root cause analysis
- production diagnostics
- long-term auditing

---

# Security Model (High-Level)

Security relies on:

- device token authentication
- member authentication
- nonce validation
- request validation
- audit logging

Sensitive data:

- tokens
- device identifiers

must never be logged in plain form.

---

# Summary

The system architecture is:

- pull-based
- workflow-driven
- time-controlled
- security-aware
- audit-focused

These properties define the operational integrity
of the system and must be preserved in all changes.

