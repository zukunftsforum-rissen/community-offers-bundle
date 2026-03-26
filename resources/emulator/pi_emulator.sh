#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${CO_EMULATOR_BASE_URL:-}"
DEVICE_ID="${CO_EMULATOR_DEVICE_ID:-pi-emulator}"
POLL_INTERVAL_SECONDS="${CO_EMULATOR_POLL_INTERVAL_SECONDS:-2}"
ONESHOT="${ONESHOT:-0}"
VERBOSE="${VERBOSE:-0}"

TOKEN_FILE="${CO_EMULATOR_TOKEN_FILE:-var/device-tokens/${DEVICE_ID}.token}"

if [[ -f "$TOKEN_FILE" ]]; then
	DEVICE_TOKEN="$(tr -d '\n' <"$TOKEN_FILE")"
	echo "Using token from file: $TOKEN_FILE"
else
	DEVICE_TOKEN="${CO_EMULATOR_TOKEN:-}"
	echo "Using token from environment"
fi

if [[ -z "$BASE_URL" ]]; then
	echo "ERROR: CO_EMULATOR_BASE_URL is empty."
	exit 1
fi

if [[ -z "$DEVICE_TOKEN" ]]; then
	echo "ERROR: No device token available."
	echo "Either set CO_EMULATOR_TOKEN or create:"
	echo "  $TOKEN_FILE"
	exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
	echo "ERROR: jq is required. Install with: sudo apt install jq"
	exit 1
fi

api_get() {
	local url="$1"
	local body_file
	body_file="$(mktemp)"
	local status

	status="$(curl -sS -o "$body_file" -w "%{http_code}" \
		-H "X-Device-Token: ${DEVICE_TOKEN}" \
		"$url")"

	printf '%s\n' "$status"
	cat "$body_file"
	rm -f "$body_file"
}

api_post_json() {
	local url="$1"
	local payload="$2"
	local body_file
	body_file="$(mktemp)"
	local status

	status="$(curl -sS -o "$body_file" -w "%{http_code}" \
		-H "Content-Type: application/json" \
		-H "X-Device-Token: ${DEVICE_TOKEN}" \
		-X POST \
		"$url" \
		-d "$payload")"

	printf '%s\n' "$status"
	cat "$body_file"
	rm -f "$body_file"
}

whoami() {
	mapfile -t response < <(api_get "${BASE_URL}/api/device/whoami")
	local status="${response[0]}"
	local body="${response[@]:1}"

	echo "WHOAMI HTTP ${status}"
	echo "$body"

	if [[ "$status" != "200" ]]; then
		echo "ERROR: whoami failed."
		exit 1
	fi
}

poll_once() {
	mapfile -t response < <(api_post_json "${BASE_URL}/api/device/poll" '{"limit":1}')
	local status="${response[0]}"
	local body="${response[@]:1}"

	[[ "$VERBOSE" == "1" ]] && echo "POLL HTTP ${status}: $body"

	case "$status" in
	200) ;;
	400)
		echo "Polling not available in current mode. Exiting."
		exit 0
		;;
	403)
		echo "Emulator not allowed in current mode. Exiting."
		exit 0
		;;
	429)
		echo "Rate limited while polling. Sleeping ${POLL_INTERVAL_SECONDS}s."
		sleep "$POLL_INTERVAL_SECONDS"
		return
		;;
	*)
		echo "Unexpected poll status ${status}. Exiting."
		exit 1
		;;
	esac

	local job_count
	job_count="$(echo "$body" | jq '.jobs | length')"

	if [[ "$job_count" -eq 0 ]]; then
		return
	fi

	local job_id nonce correlation_id area
	job_id="$(echo "$body" | jq -r '.jobs[0].jobId')"
	nonce="$(echo "$body" | jq -r '.jobs[0].nonce')"
	correlation_id="$(echo "$body" | jq -r '.jobs[0].correlationId // ""')"
	area="$(echo "$body" | jq -r '.jobs[0].area')"

	echo "Job received: id=${job_id}, area=${area}, device=${DEVICE_ID}, cid=${correlation_id}"

	confirm_job "$job_id" "$nonce" "$correlation_id"
}

confirm_job() {
	local job_id="$1"
	local nonce="$2"
	local correlation_id="$3"

	local payload
	payload="$(
		cat <<JSON
{
  "jobId": ${job_id},
  "nonce": "${nonce}",
  "ok": true,
  "correlationId": "${correlation_id}",
  "meta": {
    "source": "pi-emulator",
    "deviceId": "${DEVICE_ID}"
  }
}
JSON
	)"

	mapfile -t response < <(api_post_json "${BASE_URL}/api/device/confirm" "$payload")
	local status="${response[0]}"
	local body="${response[@]:1}"

	[[ "$VERBOSE" == "1" ]] && echo "CONFIRM HTTP ${status}: $body"

	case "$status" in
	200 | 202) ;;
	400)
		echo "Confirm not available in current mode. Exiting."
		exit 1
		;;
	403)
		echo "Emulator confirm forbidden in current mode. Exiting."
		exit 1
		;;
	429)
		echo "Rate limited while confirming."
		;;
	*)
		echo "Unexpected confirm status ${status}. Exiting."
		exit 1
		;;
	esac
}

echo "== PI Emulator starting =="
echo "BASE_URL=${BASE_URL}"
echo "DEVICE_ID=${DEVICE_ID}"
echo "POLL_INTERVAL_SECONDS=${POLL_INTERVAL_SECONDS}"
echo "TOKEN_FILE=${TOKEN_FILE}"
echo

whoami
echo

while true; do
	poll_once

	if [[ "$ONESHOT" == "1" ]]; then
		break
	fi

	sleep "$POLL_INTERVAL_SECONDS"
done
