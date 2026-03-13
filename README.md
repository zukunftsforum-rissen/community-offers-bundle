# Community Offers Bundle

[![PHP](https://img.shields.io/badge/PHP-8.4-blue)]()
[![Symfony](https://img.shields.io/badge/Symfony-6+-black)]()
[![Contao](https://img.shields.io/badge/Contao-5-orange)]()
[![License](https://img.shields.io/badge/license-MIT-green)]()
[![CI](https://github.com/zukunftsforum-rissen/community-offers-bundle/actions/workflows/ci.yml/badge.svg)]()

Open-source **Contao 5 bundle** for managing community resources and
controlling access to shared spaces.

The bundle was developed for the **Zukunftsforum Rissen** community
project and is designed to be reusable by other community initiatives.

------------------------------------------------------------------------

## Features

-   device lending management
-   door access control API
-   Raspberry Pi polling endpoint
-   door job queue
-   Contao backend integration
-   permission-based access
-   audit logging
-   device monitoring
-   browser simulator for devices

⚠️ This software controls **physical devices**. Use it at your own risk.

------------------------------------------------------------------------

## Architecture

The system uses a **secure pull architecture**.

Devices (Raspberry Pi controllers) periodically poll the server for
pending door jobs.

Member │ │ open door request ▼ Contao Backend │ │ create door job ▼
DoorJobService │ │ job available ▼ Device API ▲ │ poll │ Raspberry Pi
Device │ │ confirm execution ▼ Door opened

Workflow:

member open request ↓ door job created (pending) ↓ device poll ↓ job
dispatched ↓ device confirm ↓ executed / failed / expired

This design avoids inbound connections to the device network and
significantly reduces the attack surface.

------------------------------------------------------------------------

## API Overview

Main endpoints:

POST /api/door/open/{area} GET /api/device/poll POST /api/device/confirm
GET /api/door/whoami

Devices authenticate using dedicated **device API users**.

------------------------------------------------------------------------

## Browser Device Simulator

For development and testing the bundle includes a built-in browser
simulator.

Open:

/door-simulator

The simulator behaves like a Raspberry Pi device:

-   polls the server for door jobs
-   confirms execution
-   visualizes door openings

A simulator device (`shed-simulator`) is created automatically if it
does not yet exist.

This allows testing the complete workflow without physical hardware.

------------------------------------------------------------------------

## Hardware Setup

Typical installation:

Contao Server │ ▼ Raspberry Pi │ ▼ Shelly / relay modules │ ▼ Electric
door strike

The Raspberry Pi polls the backend and triggers relays to open doors.

------------------------------------------------------------------------

## Installation

Install via Composer:

composer require zukunftsforum-rissen/community-offers-bundle

Run the Contao database migrations:

vendor/bin/contao-console contao:migrate

No additional Symfony or Contao configuration is required.

The bundle automatically registers:

-   the `door_job` workflow
-   required services
-   the browser device simulator

------------------------------------------------------------------------

## Production Setup

In production you must configure a real `DoorGateway` implementation.

Example:

services:
ZukunftsforumRissen`\CommunityOffersBundle`{=tex}`\Door`{=tex}`\DoorGatewayInterface`{=tex}:
alias: App`\Door`{=tex}`\ShellyDoorGateway`{=tex}

This implementation should connect the backend to the actual hardware
(e.g. Shelly relays or other door control systems).

------------------------------------------------------------------------

## Logging (optional)

Logging can be enabled via environment variables:

ENABLE_LOGGING=true ENABLE_DEBUG_LOGGING=true

If not configured, logging remains disabled.

Log files are written to:

var/logs/community-offers.log

------------------------------------------------------------------------

## Documentation

Detailed documentation is available in the `docs/` directory:

docs/ ├ architecture/ ├ api/ ├ development/ ├ operations/ ├ security/ └
diagrams/

------------------------------------------------------------------------

## License

MIT
