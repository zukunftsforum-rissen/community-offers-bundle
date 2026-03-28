# Glossary

This glossary defines the core terminology used
throughout the Community Offers system.

Definitions must remain consistent with:

- code
- workflow
- API
- logs
- database

---

# Core Terms

## DoorJob

A DoorJob is:

a queued request to open a door.

Created when:

POST /api/door/open/{area}

is called.

Typical lifecycle:

pending → dispatched → executed / failed / expired

Important:

A DoorJob represents:

an executable unit of work.

---

## Dispatch

Dispatch is:

the moment a DoorJob
is assigned to a specific device.

Occurs during:

POST /api/device/poll

Result:

Job status changes:

pending → dispatched

---

## Confirm

Confirm is:

the device response
after attempting execution.

Triggered by:

POST /api/device/confirm

Important:

Confirm may result in:

executed → ok=true  
failed → ok=false  
expired → too late

Confirm must be:

idempotent.

---

## CorrelationId

CorrelationId is:

a unique identifier
used to connect related log entries.

Used for:

- tracing workflows
- debugging failures
- linking events

Typical usage:

door_open  
→ dispatch  
→ confirm  

All share:

same correlationId

---

## Device

A Device is:

a Raspberry Pi system
responsible for executing door actions.

Devices:

- authenticate using API tokens
- poll the server continuously
- execute hardware actions
- confirm execution results

Devices never:

receive inbound network connections.

---

## Area

An Area is:

a logical identifier
representing a door or access zone.

Examples:

- workshop
- sharing
- depot
- swap-house

Important:

Area values are:

validated against configuration.

---

# Supporting Terms

## Nonce

Nonce is:

a unique value
generated during dispatch.

Purpose:

- validate confirm requests
- prevent replay attacks
- ensure correct job execution

Nonce must:

match dispatched value exactly.

---

## Confirm Window

Confirm Window defines:

maximum allowed time
between:

dispatch  
and  
confirm.

If exceeded:

Job becomes:

expired

---

## Polling

Polling is:

the repeated request
from device to server
to check for jobs.

Typical interval:

~2 seconds

Important:

Polling occurs continuously.

Even when:

no jobs exist.

---

## Expired

Expired is:

a terminal DoorJob state.

Occurs when:

Confirm happens too late  
or  
confirmWindow expires.

Expired jobs:

cannot be executed.

---

## Failed

Failed is:

a terminal DoorJob state.

Occurs when:

Device reports:

ok=false

This indicates:

hardware failure
or execution problem.

---

## Executed

Executed is:

a terminal DoorJob state.

Occurs when:

Device reports:

ok=true

This indicates:

door action succeeded.

---

# Terminology Rules

These naming rules must be preserved:

Use:

expired  

Not:

timeout

Use:

ok=true / ok=false  

Not:

result=success

Use:

poll  

Not:

push

Consistency of terminology is critical
for system correctness.

