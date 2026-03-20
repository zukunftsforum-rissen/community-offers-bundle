# API Flows

The device workflow uses three main endpoints.

## Member request

POST /api/door/open/{area}

Creates a DoorJob.

## Device poll

POST /api/device/poll

The device checks if a job is available.

If a job exists, the response contains:

- jobId
- area
- nonce

## Device confirm

POST /api/device/confirm

The device reports the result of executing the job.

Payload example:

{
  "jobId": "...",
  "nonce": "...",
  "result": "success"
}