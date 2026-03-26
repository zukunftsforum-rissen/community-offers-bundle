# PI Emulator Setup

## Overview

The PI Emulator simulates a hardware device such as a Raspberry Pi and is used in **emulation mode** to test the full door workflow without real hardware.

It behaves like a real device:

1. calls `/api/device/whoami`
2. polls `/api/device/poll`
3. sends `/api/device/confirm`

---

## Modes

| Mode | Behavior |
|------|----------|
| `live` | Real hardware devices only |
| `emulation` | Emulator devices are allowed and can poll/confirm jobs |

---

## Architecture

The emulator consists of two parts:

### Bundle
The bundle provides:

- the emulator device command
- the emulator core script
- this documentation

### Project
The project provides:

- `tools/emulator/start.sh`
- `tools/emulator/run.sh`
- `tools/emulator/stop.sh`

These project scripts are thin operational wrappers for starting, stopping, logging, and supervising the emulator process.

---

## Environment

Typical `.env.local` values:

```dotenv
CO_EMULATOR_BASE_URL=https://example.org
CO_EMULATOR_DEVICE_ID=pi-emulator
CO_EMULATOR_POLL_INTERVAL_SECONDS=2

COMMUNITY_OFFERS_MODE=emulation
```

Optional:

```dotenv
CO_EMULATOR_TOKEN=your-token
CO_EMULATOR_TOKEN_FILE=var/device-tokens/pi-emulator.token
CO_EMULATOR_SCRIPT=vendor/zukunftsforum-rissen/community-offers-bundle/resources/emulator/pi_emulator.sh
```

---

## Token handling

The emulator supports two token sources:

### Preferred: token file

The emulator first looks for a token file:

```text
var/device-tokens/<deviceId>.token
```

Example:

```text
var/device-tokens/pi-emulator.token
```

### Fallback: environment variable

If no token file exists, the emulator falls back to:

```dotenv
CO_EMULATOR_TOKEN=...
```

---

## Create or update emulator device

Use the existing command:

```bash
php vendor/bin/contao-console community-offers:emulator:create-device
```

Useful options:

### Print `.env.local` snippet

```bash
php vendor/bin/contao-console community-offers:emulator:create-device --print-env-snippet
```

### Write token file automatically

```bash
php vendor/bin/contao-console community-offers:emulator:create-device --write-default-token-file
```

This will:

- create or update the emulator device
- generate a new plaintext token
- store the SHA-256 hash in `tl_co_device.apiTokenHash`
- write the plaintext token to:

```text
var/device-tokens/pi-emulator.token
```

You can also write to a custom file:

```bash
php vendor/bin/contao-console community-offers:emulator:create-device --write-token-file=var/device-tokens/pi-emulator.token
```

---

## Start emulator

```bash
tools/emulator/start.sh
```

---

## Stop emulator

```bash
tools/emulator/stop.sh`
```

---

## Logs

```bash
tail -f var/logs/pi-emulator.log
```

---

## API flow

The emulator acts as an external client and performs:

1. `GET /api/device/whoami`
2. `POST /api/device/poll`
3. `POST /api/device/confirm`

---

## Security notes

- Do not commit `.env.local`
- Do not commit plaintext device token files
- Add this to `.gitignore`:

```text
var/device-tokens/
```
