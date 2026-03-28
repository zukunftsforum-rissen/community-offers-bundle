# API Flows

This document describes the runtime interaction flows
between members, backend, and devices.

These flows represent the **actual execution sequence**
of the system.

They must remain consistent with:

- controller routes
- workflow states
- device behavior

---

# Core Device Workflow

The device workflow consists of three main endpoints.

Sequence:

Member → Open → Poll → Dispatch → Confirm

---

# 1) Member Request

POST /api/door/open/{area}

Purpose:

Creates a DoorJob.

Triggered by:

Member action in the UI.

Typical response:

HTTP 202

{
  "ok": true,
  "jobId": 123,
  "status": "pending"
}

Possible errors:

401 → not authenticated  
404 → unknown area  
429 → rate limited

Important:

This endpoint never directly triggers hardware.

It only:

creates a job.

---

# 2) Device Poll

POST /api/device/poll

Purpose:

Device checks for pending jobs.

Polling interval:

~2 seconds (fixed device behavior)

Authentication:

Device token required.

---

## Poll Response — Job Available

HTTP 200

{
  "jobs": [
    {
      "jobId": 123,
      "area": "workshop",
      "nonce": "abc123",
      "expiresInMs": 30000
    }
  ]
}

---

## Poll Response — No Job

HTTP 200

{
  "jobs": []
}

Important:

Polling always happens.

Even if:

no jobs exist.

---

# 3) Device Confirm

POST /api/device/confirm

Purpose:

Device reports job execution result.

Payload example:

{
  "jobId": 123,
  "nonce": "abc123",
  "ok": true
}

Meaning:

ok=true → execution successful  
ok=false → execution failed

---

## Confirm Responses

HTTP 200

Job confirmed successfully.

HTTP 403

Invalid device or nonce.

HTTP 404

Unknown job.

HTTP 409

Invalid job state.

HTTP 410

Job expired.

---

# Full Execution Flow

Typical successful flow:

Member  
→ POST /api/door/open/{area}  
→ Job created (pending)  

Device  
→ POST /api/device/poll  
→ Job dispatched  

Device  
→ Execute hardware  

Device  
→ POST /api/device/confirm  
→ Job executed

---

# Failure Flow — Expired

If confirm occurs too late:

Device  
→ POST /api/device/confirm  

Response:

HTTP 410

Job status becomes:

expired

---

# Timing Constraints

Important runtime timing rules:

Polling:

~2 seconds interval

Confirm window:

Configured value (e.g. 30 seconds)

If confirm exceeds:

confirmWindow

Result:

Job → expired

---

# Security Rules

All device communication:

requires authentication.

Important:

- deviceId derived from token
- nonce must match dispatch
- confirm allowed once
- replay must be prevented

---

# Flow Integrity Rules

These rules must always hold:

- Job must be pending before dispatch
- Job must be dispatched before confirm
- Expired jobs cannot be confirmed
- Confirm must be idempotent
- Device must poll continuously

Violation of these rules may cause:

- inconsistent system state
- lost job execution
- security issues

