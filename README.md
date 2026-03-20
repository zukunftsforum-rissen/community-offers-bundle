# Community Offers Bundle

[![PHP](https://img.shields.io/badge/PHP-8.4-blue)]()
[![Symfony](https://img.shields.io/badge/Symfony-6+-black)]()
[![Contao](https://img.shields.io/badge/Contao-5-orange)]()
[![License](https://img.shields.io/badge/license-MIT-green)]()
[![CI](https://github.com/zukunftsforum-rissen/community-offers-bundle/actions/workflows/ci.yml/badge.svg)]()

Open-source **Contao 5 bundle** for managing community resources and controlling access to shared spaces.

Developed for the **Zukunftsforum Rissen** and designed for reuse in similar community environments.

---

## Why this project exists

Many community spaces rely on mechanical keys.

Keys are hard to manage, easy to duplicate, and difficult to revoke.

This bundle provides a **secure digital alternative** using a **pull-based device architecture**:

* devices never accept inbound connections
* all communication is initiated by the device
* reduced attack surface

---

## Core Concepts

### Runtime Modes

| Mode     | Description                  |
| -------- | ---------------------------- |
| live     | real hardware devices        |
| emulator | emulator devices for testing |

---

### Device Types

Devices are distinguished by a single flag:

* `isEmulator = false` → real device (Raspberry Pi)
* `isEmulator = true` → emulator device

---

### Workflow

All door openings follow the same flow:

```
Member → API → OpenDoorService → WorkflowDoorGateway → Device
```

There is **no direct shortcut** and no alternative execution path.

---

### Channels (internal)

| Channel  | Target          |
| -------- | --------------- |
| physical | real hardware   |
| emulator | emulator device |

---

### Device Access Rules

| Mode     | Allowed Devices       |
| -------- | --------------------- |
| live     | real devices only     |
| emulator | emulator devices only |

---

## Features

* door access control API
* device polling endpoint
* door job workflow
* Contao backend integration
* permission-based access
* audit logging
* device monitoring
* browser-based demo UI

⚠️ **This software controls physical devices.**
Deploy only in trusted environments.

---

## API Overview

Main endpoints:

POST `/api/door/open/{area}`
GET  `/api/device/poll`
POST `/api/device/confirm`
GET  `/api/door/whoami`

Devices authenticate via dedicated **device API users**.

---

## Emulator

The emulator behaves like a real device:

* polls the API
* receives jobs
* confirms execution

This allows full workflow testing without hardware.

---

## Visual Demo

The demo is a **pure UI feature** and not part of the system logic.

Characteristics:

* no device communication
* no polling
* no confirm
* no door jobs

It is intended for:

* presentations
* explaining the workflow

---

## Architecture

The system uses a **pull-based architecture**:

```
Backend → DoorJob queue → Device polls → Job dispatch → Confirm
```

Workflow:

* member triggers door open
* job created (pending)
* device polls
* job dispatched
* device confirms
* job finalized (executed / failed / expired)

Dispatch is atomic to prevent race conditions.

---

## Hardware Setup

Typical installation:

```
Contao Server → Raspberry Pi → Relay (e.g. Shelly) → Door
```

---

## Quick Start

Install via Composer:

```
composer require zukunftsforum-rissen/community-offers-bundle
```

Run migrations:

```
vendor/bin/contao-console contao:migrate
```

Open the demo:

```
/demo
```

---

## Production Setup

You must provide a real hardware gateway:

```yaml
services:
  ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayInterface:
    alias: App\Door\ShellyDoorGateway
```

---

## Logging

Optional via environment variables:

```
ENABLE_LOGGING=true
ENABLE_DEBUG_LOGGING=true
```

Logs:

```
var/logs/community-offers.log
```

⚠️ Never enable debug logging in production.

---

## Development

Run checks:

```
composer ci
```

Includes:

* coding standards
* static analysis
* unit tests

---

## Documentation

See `docs/`:

* architecture
* api
* development
* operations
* security

---

## License

MIT
