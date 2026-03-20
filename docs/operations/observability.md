# Observability

The system provides audit logging and monitoring for door workflows.

All events are written to:

```
tl_co_door_log
```

## Logged Actions

Typical action values include:

- door_open
- door_dispatch
- door_confirm
- request_access

## Results

Result values describe the outcome of an action and may include:

- attempt
- granted
- forbidden
- unknown_area
- unauthenticated
- rate_limited
- dispatched
- confirmed
- failed
- timeout
- error

## Correlation ID

Each door workflow is assigned a **correlationId**.

This ID allows tracing a full workflow across:

- door job creation
- device polling
- dispatch
- confirmation
- audit logs

## Device Monitor

The backend device monitor derives its information from log events.

Typical indicators include:

- last poll time
- last confirm time
- last accessed area

Demo devices are excluded from production monitoring.
