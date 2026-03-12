# Observability

The system logs every workflow event to:

tl_co_door_log

Important actions:

door_open
device_poll
door_dispatch
door_confirm
door_failed
door_expired

Each entry includes a correlationId to reconstruct workflows.