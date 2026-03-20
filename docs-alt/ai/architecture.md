# System Architecture (AI Overview)

The Community Offers Bundle implements a **door access control system**
for shared community spaces.

Core components:

- Contao backend
- Door job workflow
- Raspberry Pi devices controlling doors
- Audit logging and monitoring

High level flow:

Member → Backend → DoorJob → Device Poll → Dispatch → Confirm → Door opened

Architecture characteristics:

- pull-based device communication
- job queue for door actions
- nonce-based confirmation
- audit logging of all actions

Devices never receive inbound connections.
Instead they periodically poll the server for jobs.