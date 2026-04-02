# Community Offers Bundle

[![PHP](https://img.shields.io/badge/PHP-8.4-blue)]()
[![Symfony](https://img.shields.io/badge/Symfony-7.4+-black)]()
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

- devices never accept inbound connections
- all communication is initiated by the device
- reduced attack surface

---

## Core Concepts

### Runtime Modes

| Mode     | Description                  |
|----------|------------------------------|
| live     | real hardware devices        |
| emulator | emulator devices for testing |

---

### Device Types

Devices are distinguished by a single flag:

- `isEmulator = false` → real device (Raspberry Pi)
- `isEmulator = true` → emulator device

---

### Workflow

All door openings follow the same flow:

Member  
→ API  
→ OpenDoorService  
→ DoorGatewayResolver  
→ DoorGateway  
→ Device  

There is **no direct shortcut** and no alternative execution path.

---

### Channels (internal)

| Channel  | Target          |
|----------|----------------|
| physical | real hardware   |
| emulator | emulator device |

---

### Device Access Rules

| Mode     | Allowed Devices       |
|----------|----------------------|
| live     | real devices only     |
| emulator | emulator devices only |

---

## Features

- door access control API
- device polling endpoint
- door job workflow
- Contao backend integration
- permission-based access
- audit logging
- device monitoring
- browser-based demo UI

⚠️ **This software controls physical devices.**  
Deploy only in trusted environments.

---

## API Overview

Main endpoints:

POST /api/door/open/{area}  
GET  /api/device/poll  
POST /api/device/confirm  
GET  /api/door/whoami  

Devices authenticate via dedicated **device API users**.

---

## Emulator

The emulator behaves like a real device:

- polls the API
- receives jobs
- confirms execution

This allows full workflow testing without hardware.

---

## Visual Demo

The demo is a **pure UI feature** and not part of the system logic.

Characteristics:

- no device communication
- no polling
- no confirm
- no door jobs

It is intended for:

- presentations
- explaining the workflow

---

## Architecture

The system uses a **pull-based architecture**:

Backend → DoorJob queue → Device polls → Job dispatch → Confirm

Workflow:

- member triggers door open
- job created (pending)
- device polls
- job dispatched
- device confirms
- job finalized (executed / failed / expired)

Dispatch is atomic to prevent race conditions.

---

## Hardware Setup

Typical installation:

Contao Server → Raspberry Pi → Relay (e.g. Shelly) → Door

---

## Quick Start

Install via Composer:

composer require zukunftsforum-rissen/community-offers-bundle

Run migrations:

vendor/bin/contao-console contao:migrate

Open the demo:

/demo

---

## Configuration

Add required environment variables to `.env.local`.

### Required

COMMUNITY_OFFERS_MODE=live  

COMMUNITY_OFFERS_MAIL_FROM=noreply@example.org  
COMMUNITY_OFFERS_MAIL_REPLY_TO=info@example.org  

COMMUNITY_OFFERS_APP_URL=https://example.org/app  

---

### Areas

Defines the available areas.

COMMUNITY_OFFERS_AREAS='[
  {"slug":"workshop","title":"Workshop"},
  {"slug":"sharing","title":"Sharing"}
]'

---

### Area Groups

Maps areas to Contao group IDs.

COMMUNITY_OFFERS_AREA_GROUPS='{
  "workshop": 3,
  "sharing": 5
}'

---

### Timing Settings

COMMUNITY_OFFERS_DOI_TTL=172800  
COMMUNITY_OFFERS_PASSWORD_TTL=86400  
COMMUNITY_OFFERS_CONFIRM_WINDOW=30  

---

### Optional

COMMUNITY_OFFERS_LOGIN_IDENTIFIER=email  

ENABLE_LOGGING=true  
ENABLE_DEBUG_LOGGING=false  


## Login Identifier

The bundle supports different login identifiers.

Typical values:

username  
email  

Recommended:

email  

---

### Using `email` as login identifier

If the login identifier is set to:

```
COMMUNITY_OFFERS_LOGIN_IDENTIFIER=email
```

it is recommended to extend the Contao login template.

This improves:

- field labeling
- usability
- clarity for users

---

### Template Extension

Create a custom login template:

```
templates/frontend_module/mod_login_email.html.twig
```

Content:

```twig
{% extends "@CommunityOffers/frontend_module/mod_login_dynamic_username.html.twig" %}

{#
    Dieses Template erweitert das Standard-Login-Template
    und überschreibt nur den Username-Block.
#}
```

---

### Activate Template

In the Contao backend:

Login module → Template:

```
mod_login_email
```

---

### Note

Even when using email as login identifier:

- the internal username remains stable
- the email address can still be changed
- login remains consistent


### Full Minimal Example (.env.local)

Example:

```
COMMUNITY_OFFERS_MODE=live

COMMUNITY_OFFERS_LOGIN_IDENTIFIER=email

COMMUNITY_OFFERS_MAIL_FROM=noreply@example.org
COMMUNITY_OFFERS_MAIL_REPLY_TO=info@example.org
COMMUNITY_OFFERS_INFO_ADDRESS=info@example.org

COMMUNITY_OFFERS_APP_URL=https://example.org/app
COMMUNITY_OFFERS_RESET_PASSWORD_URL=https://example.org/reset-password

COMMUNITY_OFFERS_AREAS='[
  {"slug":"workshop","title":"Workshop"},
  {"slug":"sharing","title":"Sharing"},
  {"slug":"depot","title":"Depot"},
  {"slug":"swap-house","title":"Swap House"}
]'

COMMUNITY_OFFERS_AREA_GROUPS='{
  "workshop": 3,
  "sharing": 5,
  "depot": 7,
  "swap-house": 9
}'

COMMUNITY_OFFERS_DOI_TTL=172800
COMMUNITY_OFFERS_PASSWORD_TTL=86400
COMMUNITY_OFFERS_CONFIRM_WINDOW=30

ENABLE_LOGGING=true
ENABLE_DEBUG_LOGGING=false
```

---

# Access Request Types

The system supports two fundamentally different access request workflows.

These workflows must remain clearly separated in both implementation
and documentation.

---

## Initial Access Request (First Application)

Used when:

A person does not yet have a member account.

Typical flow:

1. User fills access request form
2. Access request is stored
3. DOI email is sent
4. User confirms email address
5. Member account is created
6. User sets password
7. Admin approves request

Result:

A new member (`tl_member`) is created.

Key characteristics:

- Requires form input
- Requires password setup
- Creates new member
- Login disabled until admin approval

---

## Additional Access Request (Additional Rights)

Used when:

A member already exists.

Typical flow:

1. Member clicks request inside the app
2. DOI email is sent
3. Member confirms email
4. Admin approves request
5. Additional permissions/groups assigned

Result:

Existing member receives additional permissions.

Key characteristics:

- No form required
- No password step
- No new member created
- Only permissions/groups are modified

---

## Why This Separation Matters

These workflows differ in:

- Member lifecycle handling
- Security behavior
- Database writes
- Permission management
- Audit logging

Mixing these workflows may cause:

- Duplicate members
- Incorrect permissions
- Broken login flows
- Inconsistent audit history

---

## Production Setup

You must provide at least one gateway that supports **live mode**.

Example:

services:

  App\Door\ShellyDoorGateway:
    tags:
      - { name: community_offers.door_gateway }

  ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayInterface:
    alias: App\Door\ShellyDoorGateway

The DoorGatewayResolver selects the appropriate gateway based on runtime mode.

---

## Logging

Optional via environment variables:

ENABLE_LOGGING=true  
ENABLE_DEBUG_LOGGING=true  

Logs:

var/logs/community-offers.log

⚠️ Never enable debug logging in production.

---

## Development

Run checks:

composer ci

Includes:

- coding standards
- static analysis
- unit tests

---

## Documentation

See docs/:

- architecture
- api
- development
- operations
- security

---

## License

MIT

---


## Frontend / PWA

See:

docs/frontend/app-pwa.md
