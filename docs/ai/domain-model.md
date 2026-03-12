# Domain Model

Main concepts used in the system.

## DoorJob

Represents a request to open a door.

Lifecycle states typically include:

pending → dispatched → executed
                        ↓
                     failed
pending → expired

Important attributes include:

- area
- correlationId
- nonce
- requestedByMemberId
- dispatchToDeviceId

## Device

Represents a Raspberry Pi controlling one or more doors.

Devices:

- authenticate via API user
- poll the server for jobs
- execute door actions
- confirm execution

## DoorLog

Audit log entries stored in:

tl_co_door_log

Logs contain:

- action
- result
- deviceId
- memberId
- correlationId

Used for tracing workflows and monitoring.