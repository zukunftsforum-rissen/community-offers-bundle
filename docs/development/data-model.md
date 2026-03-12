# Data Model

## tl_co_door_job

Represents a door job created when a member requests to open a door.

Typical fields:

- id
- area
- correlationId
- requestedByMemberId
- requestIp
- userAgent
- nonce
- dispatchToDeviceId
- status
- createdAt
- createdAtMs
- expiresAt
- dispatchedAt
- dispatchedAtMs
- executedAt
- executedAtMs
- attempts
- resultCode
- resultMessage

Status values typically include:

- pending
- dispatched
- executed
- failed
- expired

## tl_co_door_log

Audit log for door workflow events.

Important fields:

- id
- tstamp
- correlationId
- deviceId
- memberId
- area
- action
- result
- ip
- userAgent
- message
- context

Actions include examples such as:

- door_open
- door_dispatch
- door_confirm
- request_access

Results represent the outcome of the action, for example:

- granted
- forbidden
- rate_limited
- dispatched
- confirmed
- failed
- timeout
