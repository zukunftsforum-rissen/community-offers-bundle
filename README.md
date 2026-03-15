# Community Offers Bundle

[![PHP](https://img.shields.io/badge/PHP-8.4-blue)]()
[![Symfony](https://img.shields.io/badge/Symfony-6+-black)]()
[![Contao](https://img.shields.io/badge/Contao-5-orange)]()
[![License](https://img.shields.io/badge/license-MIT-green)]()
[![CI](https://github.com/zukunftsforum-rissen/community-offers-bundle/actions/workflows/ci.yml/badge.svg)]()

Open-source **Contao 5 bundle** for managing community resources and controlling access to shared spaces.

The bundle was developed for the **Zukunftsforum Rissen** community project and is designed to be reusable by other community initiatives.

---

## Why this project exists

Many community spaces rely on simple mechanical keys.

Keys are hard to manage, easy to duplicate, and difficult to revoke.

This bundle provides a **secure digital alternative** that integrates with an existing web infrastructure and keeps the device network isolated from the internet.

The system uses a **pull-based device architecture**, where hardware devices periodically poll the backend for jobs.

This avoids inbound connections to devices and significantly reduces the attack surface.

---

## Use Cases

Typical environments where this bundle can be used:

- community workshops
- neighborhood sheds
- maker spaces
- club buildings
- shared tool libraries
- community gardens

---

## Features

- device lending management
- door access control API
- Raspberry Pi polling endpoint
- door job queue
- Contao backend integration
- permission-based access
- audit logging
- device monitoring
- browser simulator for devices

⚠️ **This software controls physical devices.**  
Deploy it only in trusted environments and carefully review authentication and network security.

---

## Architecture

The system uses a **secure pull architecture**.

Devices (Raspberry Pi controllers) periodically poll the server for pending door jobs.

Member → Contao Backend → DoorJobService → Device API ← Raspberry Pi Device → Door opened

Workflow:

member open request  
↓  
door job created (pending)  
↓  
device poll  
↓  
job dispatched  
↓  
device confirm  
↓  
executed / failed / expired  

Dispatch operations are atomic to prevent race conditions when multiple device polls occur.

Each door job can only be dispatched once.

---

## API Overview

Main endpoints:

POST /api/door/open/{area}  
GET  /api/device/poll  
POST /api/device/confirm  
GET  /api/door/whoami  

Devices authenticate using dedicated **device API users**.

These credentials must be restricted to device endpoints only and should never be reused for backend or member access.

---

## Browser Device Simulator

For development and testing the bundle includes a built-in browser simulator.

Open:

/door-simulator

The simulator behaves like a Raspberry Pi device:

- polls the server for door jobs
- confirms execution
- visualizes door openings

A simulator device (`shed-simulator`) is created automatically if it does not yet exist.

This allows testing the complete workflow without physical hardware.

---

## Hardware Setup

Typical installation:

Contao Server → Raspberry Pi → Shelly / relay modules → Electric door strike

The Raspberry Pi polls the backend and triggers relays to open doors.

---

## Quick Start

Install via Composer:

composer require zukunftsforum-rissen/community-offers-bundle

Run Contao migrations:

vendor/bin/contao-console contao:migrate

Start the simulator:

/door-simulator

You can now test the complete workflow without hardware.

---

## Production Setup

In production you must configure a real `DoorGateway` implementation.

Example:

services:
  ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayInterface:
    alias: App\Door\ShellyDoorGateway

This implementation should connect the backend to the actual hardware (e.g. Shelly relays or other door control systems).

---

## Compatibility

| Component | Version |
|-----------|--------|
| PHP | 8.4 |
| Contao | 5.x |
| Symfony | 6+ |

---

## Logging (optional)

Logging can be enabled via environment variables:

ENABLE_LOGGING=true  
ENABLE_DEBUG_LOGGING=true

If not configured, logging remains disabled.

Log files are written to:

var/logs/community-offers.log

Debug logging should **never be enabled in production environments**.

Sensitive data such as IP addresses should be anonymized before logging.

---

## Development

Run quality checks locally:

composer ci

This executes:

- coding standards
- static analysis
- unit tests

---

## Documentation

Detailed documentation is available in the `docs/` directory:

docs/
 ├ architecture/
 ├ api/
 ├ development/
 ├ operations/
 ├ security/
 └ diagrams/

---

## License

MIT
