#!/usr/bin/env bash

set -e

while true; do
	echo "Starting emulator..."
	tools/emulator/pi_emulator.sh || true
	echo "Emulator stopped. Restarting in 5 seconds..."
	sleep 5
done
