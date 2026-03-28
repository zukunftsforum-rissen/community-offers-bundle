# Observability

The system provides audit logging and monitoring
for door workflows and device activity.

All relevant workflow events are written to:

```
tl_co_door_log
```

Device state information is additionally stored in:

```
tl_co_device.lastSeen
```

---

# Logged Actions

Typical **door workflow** actions include:

- door_open
- door_dispatch
- door_confirm
- door_failed
- door_expired

Additional **access workflow** actions may include:

- request_access

Note:

`request_access` belongs to the **access request workflow**
and is not part of the door execution workflow itself.

---

# Result Values

Result values describe the outcome of an action.

Typical values include:

- attempt
- granted
- forbidden
- unknown_area
- unauthenticated
- rate_limited
- dispatched
- confirmed
- failed
- expired
- error

Note:

Earlier documentation referenced `timeout`.
The current workflow terminology uses:

```
expired
```

---

# Correlation ID

Each door workflow is assigned a **correlationId**.

This ID allows tracing a full workflow across:

- door job creation
- device polling
- job dispatch
- device confirmation
- audit log entries

The correlationId is written consistently into:

- API logs
- tl_co_door_log
- workflow timeline

This enables:

- complete workflow reconstruction
- debugging of distributed steps
- post-event diagnostics

---

# Device Monitoring

The backend device monitor derives its information from:

- device poll events
- confirm events
- lastSeen timestamps

Important data sources:

- tl_co_device.lastSeen
- recent device_poll events
- recent device_confirm events

Typical indicators include:

- last poll time
- last confirm time
- last accessed area
- device online/offline status

Online/offline status is derived from:

- time since lastSeen
- recent activity

---

# Demo and Emulator Behavior

Monitoring distinguishes between:

- real devices
- emulator devices
- demo mode activity

Rules:

- Demo activity is excluded from production monitoring
- Emulator devices may be included depending on configuration
- Real devices are always monitored

This prevents:

- misleading device state information
- pollution of operational dashboards

---

# Observability Design Goals

The observability layer supports:

- full auditability of door access
- traceability across system components
- operational monitoring
- incident investigation
- regulatory accountability (if required)

All critical workflow steps are therefore:

- logged
- timestamped
- correlation-aware
