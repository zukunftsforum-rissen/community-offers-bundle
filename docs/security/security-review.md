# Security Model

## Pull Architecture

Devices poll the server. No inbound network access to devices is required.

## Authentication

Devices authenticate via device API users.

## Nonce Protection

Each dispatched job contains a nonce.

The device must return this nonce when confirming execution.

This protects against:

- replay attacks
- unauthorized confirm calls

## Rate Limiting

DeviceRateLimitService
DeviceConfirmRateLimitService

These prevent API abuse.

## Audit Logging

All workflow steps are logged in tl_co_door_log.
Each workflow contains a correlationId for traceability.