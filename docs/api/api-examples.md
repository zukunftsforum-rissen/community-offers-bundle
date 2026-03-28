
# API Examples — Door Access System

Diese Beispiele zeigen typische API-Aufrufe
für Member und Devices.

Wichtig:

- Authentifizierung erfolgt über Header
- deviceId wird NICHT im URL-Pfad übergeben
- deviceId wird aus dem Token abgeleitet

<member-api-token>   Beispieltoken eines Members
<device-api-token>   Beispieltoken eines Devices

---

# Tür öffnen (Member)

POST /api/door/open/{area}

Beispiel:

curl -X POST https://example.de/api/door/open/workshop \
  -H "X-Member-Token: <member-api-token>" \
  -H "X-Correlation-Id: 550e8400-e29b-41d4-a716-446655440000"

Typische Antwort:

HTTP 202

{
  "ok": true,
  "jobId": 123,
  "status": "pending",
  "expiresAt": 1774121515,
  "mode": "emulator"
}

mode:

- "live"
- "emulator"

---

# Door Status (PWA Polling)

GET /api/door/status/{jobId}

Beispiel:

curl https://example.de/api/door/status/123 \
  -H "X-Member-Token: <member-api-token>"

Antwort:

{
  "ok": true,
  "jobId": 123,
  "status": "executed"
}

Mögliche Status:

- pending
- dispatched
- executed
- failed
- expired

---

# Device Poll

POST /api/device/poll

Beispiel:

curl -X POST https://example.de/api/device/poll \
  -H "X-Device-Token: <device-api-token>"

Antwort mit Job:

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

expiresInMs:

Restzeit bis Confirm-Deadline (Millisekunden).

Antwort ohne Job:

HTTP 200

{
  "jobs": []
}

Wichtig:

Polling erfolgt kontinuierlich
(typisch ca. alle 2 Sekunden),
auch wenn keine Jobs vorhanden sind.

---

# Confirm Success

POST /api/device/confirm

Beispiel:

curl -X POST https://example.de/api/device/confirm \
  -H "X-Device-Token: <device-api-token>" \
  -H "Content-Type: application/json" \
  -d '{"jobId":123,"nonce":"abc","ok":true}'

Antwort:

HTTP 200

{
  "ok": true
}

---

# Confirm erneut (Idempotent)

POST /api/device/confirm

Beispiel:

curl -X POST https://example.de/api/device/confirm \
  -H "X-Device-Token: <device-api-token>" \
  -H "Content-Type: application/json" \
  -d '{"jobId":123,"nonce":"abc","ok":true}'

Antwort:

HTTP 200

{
  "ok": true
}

Hinweis:

Mehrfaches Confirm mit gleicher nonce
führt zu keiner weiteren Statusänderung.

---

# Confirm Failed (Hardwarefehler)

POST /api/device/confirm

Beispiel:

curl -X POST https://example.de/api/device/confirm \
  -H "X-Device-Token: <device-api-token>" \
  -H "Content-Type: application/json" \
  -d '{"jobId":123,"nonce":"abc","ok":false}'

Antwort:

HTTP 200

{
  "ok": false
}

---

# Confirm Expired (zu spät)

HTTP 410

{
  "ok": false,
  "error": "expired"
}

Hinweis:

"expired" ist korrekt  
nicht:

"confirm_timeout"

---

# Rate Limit Beispiel

HTTP 429

Header:

Retry-After: 12

Body:

{
  "ok": false,
  "error": "rate_limited",
  "retryAfterSeconds": 12
}

---

# Whoami (Member)

GET /api/door/whoami

Beispiel:

curl https://example.de/api/door/whoami \
  -H "X-Member-Token: <member-api-token>"

Antwort:

{
  "memberId": 42,
  "areas": ["workshop", "sharing"]
}

---

# Whoami (Device)

GET /api/device/whoami

Beispiel:

curl https://example.de/api/device/whoami \
  -H "X-Device-Token: <device-api-token>"

Antwort:

{
  "deviceId": "pi-01",
  "areas": ["workshop"],
  "isEmulator": false
}
