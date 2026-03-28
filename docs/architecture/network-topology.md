# Network Topology

The Community Offers Bundle uses a **poll-based architecture**
so that no inbound connections to the device network are required.

This significantly reduces the attack surface.

---

# Physical Setup

Typical physical topology:

Internet  
↓  
Contao Server (Hosting / API)  
↓  
FritzBox (Home Network)  
↓  
Guest LAN (recommended)  
↓  
LAN Cable to Shed  
↓  
Secondary Router or Switch  
↓  
Raspberry Pi Device Controller  
↓  
Shelly / Relay Modules  
↓  
Door Locks  

Optional:

Emulator Device  
→ runs in test or diagnostic environments  
→ communicates with the same backend API  

---

# Security Design

This architecture intentionally avoids direct access to the device network.

Advantages:

• No port forwarding required  
• Devices are not reachable from the internet  
• Communication is initiated only by devices  
• Reduced attack surface  
• No inbound firewall rules required  

Recommended:

• Use separate VLAN or Guest LAN  
• Restrict inbound connections to device network  
• Allow outbound HTTPS only  

---

# Device Communication

Devices periodically poll the backend.

**Endpoint:**

POST /api/device/poll

**Polling behavior:**

- Poll interval: **2 seconds**
- Interval is currently **hardcoded**
- Polling continues even if no jobs exist
- No idle backoff is implemented

If a job exists:

dispatch → execute → confirm

Otherwise:

server returns an empty job response.

---

# Hardware Overview

Typical setup:

Raspberry Pi  
→ controls relay modules (Shelly or similar)  
→ relays control electric door strikes  

The Raspberry Pi communicates **only outbound**
with the backend API using HTTPS.

No direct incoming connections are required.

---

# Notes on Network Stability

Because polling occurs every 2 seconds:

Expected request load:

1 Device → 30 requests / minute  
5 Devices → 150 requests / minute  
10 Devices → 300 requests / minute  

This should be considered when sizing:

- server capacity  
- reverse proxy limits  
- logging throughput  

In typical small installations,
this load is well within safe limits.
