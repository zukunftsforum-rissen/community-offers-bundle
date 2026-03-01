# community-offers-bundle

## Test Levels

This bundle currently uses `phpunit` unit tests (see `tests/Unit`).

### Unit tests

- Scope: one class/service/controller with all external boundaries replaced by test doubles.
- Boundaries mocked/stubbed: Contao `Database` singleton, router, mailer, cache, framework init.
- No Symfony kernel boot, no real database connection, no real HTTP stack.

### Workflow-style unit tests

Some tests cover multi-step business flows (for example create request -> DOI confirm -> resend state checks in `AccessRequestServiceTest`, and member open door -> device poll -> device confirm in `DeviceControllerTest`, including timeout handling, device binding, and area-based job filtering).

Covered device workflow scenarios include:

- open -> poll -> confirm success (`accepted=true`, final `executed`)
- open -> poll -> confirm failure (`ok=false`, final `failed`)
- open -> poll -> confirm after timeout (`accepted=false`, final `expired/TIMEOUT`)
- open -> poll by device A, confirm by device B (`accepted=false`, job remains bound to device A)
- open in area X, poll by device with area Y (no jobs dispatched)

- These are still unit tests in this project.
- They are integration-like in flow coverage, but still isolated by doubles.

### Integration / E2E tests

Not part of the current `Unit` suite.

- Integration tests would boot more framework/runtime pieces and use real persistence or HTTP boundaries.
- E2E tests would exercise full user flows through real endpoints/UI.

### Run tests

```bash
composer run test:unit
```

## Recent test coverage additions (2026-03)

- Added workflow-style unit tests for access request and device lifecycles.
- Extended device workflow coverage with timeout handling, device binding checks, and area-filtered polling.
- Improved test-level documentation in key unit test classes and README.