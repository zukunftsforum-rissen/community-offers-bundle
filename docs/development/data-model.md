# Data Model

## tl_co_door_job

id
area
deviceId
status
nonce
createdAt
executedAt

Status values:

pending
dispatched
executed
failed
expired

## tl_co_door_log

id
jobId
deviceId
area
action
correlationId
createdAt