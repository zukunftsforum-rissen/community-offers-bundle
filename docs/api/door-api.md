
# Door Device API

Dieses Dokument beschreibt die HTTP‑API
zwischen Backend und Device (z. B. Raspberry Pi).

Wichtig:

- Geräte authentifizieren sich über Token
- deviceId wird aus der Authentifizierung abgeleitet
- deviceId wird **nicht** im URL‑Pfad übergeben
- Kommunikation erfolgt ausschließlich per HTTPS

---

# 1) Open Door (Member → Server)

POST /api/door/open/{area}

Erstellt einen neuen Door‑Job.

Typische Antwort:

HTTP 202

{
  "ok": true,
  "jobId": 123,
  "status": "pending",
  "expiresAt": 1774121515,
  "mode": "live"
}

mode:

- "live"
- "emulator"

Wichtig:

mode bestimmt UI‑Verhalten und Debug‑Kontext.

Mögliche Fehler:

401  
→ nicht eingeloggt

404  
→ area unbekannt

429  
→ Rate Limit aktiv

Bei 429:

Header:

Retry-After: <seconds>

---

# 1b) Door Status (PWA Polling)

GET /api/door/status/{jobId}

Gibt aktuellen Status eines Jobs zurück.

Typische Antwort:

{
  "ok": true,
  "jobId": 123,
  "status": "executed",
  "executedAt": 1774121522
}

Mögliche Status:

- pending
- dispatched
- executed
- failed
- expired

Wichtig:

expired ist ein terminaler Zustand.

---

# 2) Device Poll

POST /api/device/poll

Devices pollen diesen Endpoint **kontinuierlich**,
auch wenn keine Jobs vorhanden sind.

Typisches Intervall:

ca. 2 Sekunden

Konfigurationsbezug:

confirmWindow basiert auf:

community_offers.confirm_window

Wenn Jobs vorhanden:

HTTP 200

{
  "jobs": [
    {
      "jobId": 123,
      "area": "workshop",
      "nonce": "abc123...",
      "expiresInMs": 30000
    }
  ]
}

expiresInMs:

Restzeit bis zum Confirm‑Deadline
(in Millisekunden).

Wenn keine Jobs vorhanden:

HTTP 200

{
  "jobs": []
}

---

# 3) Device Confirm

POST /api/device/confirm

Bestätigt die Ausführung eines Jobs.

Payload:

{
  "jobId": 123,
  "nonce": "abc123...",
  "ok": true,
  "meta": {}
}

Bedeutung:

ok=true  
→ Tür erfolgreich ausgeführt

ok=false  
→ Fehler bei Hardware

nonce:

- gültig nur während dispatched‑Status
- muss exakt übernommen werden

---

## Typische Antworten

HTTP 200

→ Job erfolgreich bestätigt  
→ oder idempotent bestätigt

Idempotenz:

Mehrfaches Senden desselben Confirm
mit identischer nonce liefert:

HTTP 200

ohne erneute Statusänderung.

---

HTTP 403

→ device oder nonce falsch

HTTP 404

→ jobId unbekannt

HTTP 409

→ Job nicht im Status dispatched

HTTP 410

→ Job expired  
→ Confirm zu spät

Wichtig:

Status:

expired

(nicht timeout)

expired ist terminal.

---

# 4) Whoami (Member)

GET /api/door/whoami

Gibt Informationen über den eingeloggten Member zurück.

Typische Antwort:

{
  "memberId": 42,
  "areas": ["workshop", "sharing"]
}

---

# 5) Whoami (Device)

GET /api/device/whoami

Gibt Informationen über das authentifizierte Device zurück.

Typische Antwort:

{
  "deviceId": "pi-01",
  "areas": ["workshop"],
  "isEmulator": false
}

---

# 6) Correlation-ID (empfohlen)

Optional kann ein Header gesetzt werden:

X-Correlation-Id: <uuid>

Wenn nicht vorhanden:

Server erzeugt automatisch eine ID.

Diese wird verwendet für:

- Logging
- Audit
- Debugging

---

# 7) Timing-Verhalten

Wichtige Parameter:

confirmWindow:

Konfigurierbar über:

community_offers.confirm_window

Typisch:

30 Sekunden

Polling:

typisch ca. 2 Sekunden

Polling erfolgt **immer**,
auch ohne aktive Jobs.

Wenn Confirm zu spät erfolgt:

Antwort:

HTTP 410  
Status:

expired

---

# 8) Sicherheitshinweise

Wichtig:

- Nonce muss exakt übernommen werden
- Confirm darf nur einmal logisch wirksam sein
- Token darf nicht geloggt werden
- TLS ist zwingend erforderlich

---

# 9) Typischer Ablauf (Kurzform)

Member:

POST /api/door/open/{area}

↓

PWA:

GET /api/door/status/{jobId}

↓

Device:

POST /api/device/poll

↓

Device:

POST /api/device/confirm

↓

Server:

Job → executed / failed / expired
