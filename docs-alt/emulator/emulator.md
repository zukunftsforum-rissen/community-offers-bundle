# PI Emulator Setup

## Overview

The PI Emulator simulates a hardware device (e.g. Raspberry Pi) and is used in **emulator mode** to test the full door workflow without real hardware.

---

## Modes

| Mode        | Behavior |
|------------|----------|
| live       | Real hardware only |
| emulator  | Emulator acts as device |

---

## Environment (.env.local)

```
CO_EMULATOR_BASE_URL=https://example.org
CO_EMULATOR_DEVICE_ID=pi-emulator
CO_EMULATOR_TOKEN=your-token
CO_EMULATOR_POLL_INTERVAL_SECONDS=2

COMMUNITY_OFFERS_MODE=emulator
```

---

## Start Emulator

```
tools/emulator/start.sh
```

---

## Stop Emulator

```
tools/emulator/stop.sh
```

---

## Logs

```
tail -f var/logs/pi-emulator.log
```

---

## How it works

The emulator behaves like a device:

1. Calls `/api/device/whoami`
2. Polls `/api/device/poll`
3. Sends `/api/device/confirm`

---

## Process handling

### nohup

`nohup` ensures the process keeps running after SSH disconnect.

### &

Runs the process in the background.

### run.sh

Loop script:

- restarts emulator on crash
- ensures resilience

---

## Important

- Emulator is **not part of Symfony app**
- It is an **external client**
- `.env.local` must not be committed
