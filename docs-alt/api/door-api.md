# Door Device API

The device API is used by Raspberry Pi controllers to interact with the backend.

Device authentication is performed using dedicated **device API users**.
The `deviceId` is derived from the authenticated device user and **not passed in the URL**.

## Open Door

```
POST /api/door/open/{area}
```

Creates a new door job for the requested area.

Response indicates whether the request was accepted.

## Device Poll

```
POST /api/device/poll
```

Devices periodically poll this endpoint to check for pending door jobs.

The device identity is derived from authentication.

If a job is available the response includes:

- jobId
- area
- nonce

The device should execute the action and then call `/api/device/confirm`.

## Device Confirm

```
POST /api/device/confirm
```

The device confirms that the job was executed.

Payload example:

```
{
  "jobId": "...",
  "nonce": "...",
  "result": "success"
}
```

The nonce must match the nonce provided in the dispatch response.

## Whoami (Member)

```
GET /api/door/whoami
```

Returns information about the authenticated member and their allowed areas.

## Whoami (Device)

```
GET /api/device/whoami
```

Returns information about the authenticated device.
