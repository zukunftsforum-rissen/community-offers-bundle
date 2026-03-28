# Emulator Setup

## Overview

The **Device Emulator** simulates a hardware device such as a Raspberry Pi.
It is used in **emulator mode** to test the complete door workflow
without requiring physical hardware.

The emulator behaves like a real device:

1. calls `/api/device/whoami`
2. polls `/api/device/poll`
3. sends `/api/device/confirm`

---

# Modes

| Mode | Behavior |
|------|----------|
| `live` | Real hardware devices only |
| `emulator` | Emulator devices are allowed and can poll/confirm jobs |

Note:

Earlier documentation referenced `emulation`.
The correct mode name is:

`emulator`

---

# Architecture

The emulator consists of two layers.

## Bundle Layer

The bundle provides:

- emulator device creation command  
- emulator core script  
- runtime integration  
- documentation  

## Project Layer

The project provides operational wrapper scripts:

- `tools/emulator/start.sh`
- `tools/emulator/run.sh`
- `tools/emulator/stop.sh`

These scripts handle:

- process lifecycle  
- logging  
- supervision  

---

# Environment Configuration

Typical `.env.local` values:

```dotenv
CO_EMULATOR_BASE_URL=https://example.org
CO_EMULATOR_DEVICE_ID=pi-emulator
CO_EMULATOR_POLL_INTERVAL_SECONDS=2

COMMUNITY_OFFERS_MODE=emulator
```

Important:

`COMMUNITY_OFFERS_MODE` must be:

emulator

(not `emulation`)

---

# Token Handling

The emulator supports two token sources.

## Preferred: Token File

The emulator first checks for:

```text
var/device-tokens/<deviceId>.token
```

Example:

```text
var/device-tokens/pi-emulator.token
```

This approach:

- avoids storing tokens in environment files  
- improves security  
- supports rotation  

---

## Fallback: Environment Variable

If no token file exists, the emulator falls back to:

```dotenv
CO_EMULATOR_TOKEN=...
```

---

# Create or Update Emulator Device

Use the command:

```bash
php vendor/bin/contao-console community-offers:emulator:create-device
```

Useful options:

## Print `.env.local` snippet

```bash
php vendor/bin/contao-console community-offers:emulator:create-device --print-env-snippet
```

---

## Write token file automatically

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

---

## Write token to custom file

```bash
php vendor/bin/contao-console community-offers:emulator:create-device --write-token-file=var/device-tokens/pi-emulator.token
```

---

# Start Emulator

```bash
tools/emulator/start.sh
```

---

# Stop Emulator

```bash
tools/emulator/stop.sh
```

(Note: removed stray backtick from original documentation.)

---

# Logs

```bash
tail -f var/logs/pi-emulator.log
```

---

# API Flow

The emulator acts as an external client.

Sequence:

1. `GET /api/device/whoami`
2. `POST /api/device/poll`
3. `POST /api/device/confirm`

Important:

Polling interval is currently:

2 seconds (continuous, no idle backoff)

---

# Security Notes

Recommended:

- Do not commit `.env.local`
- Do not commit plaintext device token files
- Store tokens only in secure locations

Add to `.gitignore`:

```text
var/device-tokens/
```

---

# Operational Notes

If the emulator fails to authenticate:

Check:

- token file exists
- token matches database hash
- device is enabled
- mode is set to `emulator`

If polling fails:

Check:

- API URL
- network connectivity
- HTTPS certificates
