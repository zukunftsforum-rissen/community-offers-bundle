# Network Topology

The Community Offers Bundle uses a **poll-based architecture** so that
no inbound connections to the device network are required.

This significantly reduces the attack surface.

## Physical Setup

Internet
   ↓
Contao Server (Hosting)
   ↓
FritzBox (Home Network)
   ↓
Guest LAN
   ↓
LAN Cable to Shed
   ↓
Secondary Router / Switch
   ↓
Raspberry Pi Device Controller
   ↓
Shelly / Relay Modules
   ↓
Door Locks

## Security Design

This architecture intentionally avoids direct access to the device network.

Advantages:

• No port forwarding required  
• Devices are not reachable from the internet  
• Communication is initiated only by devices  
• Reduced attack surface  

## Device Communication

Devices periodically poll the backend:

GET /api/device/poll

If a job exists:

dispatch → execute → confirm

## Hardware Overview

Typical setup:

Raspberry Pi  
→ controls relay modules (Shelly or similar)  
→ relays control electric door strikes

The Pi communicates only with the backend API.