# Universal Lab Analyzer Integration

## Overview
Analyzer-agnostic LIS integration for AccumenAI — supports any lab analyzer
that speaks ASTM, HL7, or vendor-specific protocols.

## Architecture
```
Analyzer → Local Gateway → Protocol Adapter → Universal Result → REST API → LIS DB → UI
```

## Phases Delivered
1. Foundation (tables, models, interface)
2. Protocol Parsers (ASTM, HL7, CSV)
3. Sysmex XN-550 Adapter (5-part)
4. API + Gateway MVP
5. Admin UI
6. Offline Queue + Retry Hardening
7. Analyzer Simulator (17 scenarios)
8. Mindray BC-5150 Adapter (3-part, extensibility proof)
9. Production Hardening

## Supported Analyzers
| Vendor | Model | Differential | Adapter |
|--------|-------|--------------|---------|
| Sysmex | XN-550 | 5-part | sysmex_xn |
| Mindray | BC-5150 | 3-part | mindray_bc |

## Adding a New Analyzer (Template)
1. Create adapter class extending `VendorAdapterBase`
2. Create parameter map seeder
3. Add 3 fixtures
4. Register in `AppServiceProvider` (1 line)
5. Add fixtures to `LabValidateFixtures` command
6. Write adapter + seeder tests

**Zero core refactoring required.**

## Endpoints
- `POST /api/lab-gateway/results`
- `GET  /api/lab-gateway/worklist`
- `POST /api/lab-gateway/ack`
- `POST /api/lab-gateway/health`
- `GET  /api/lab-gateway/results/check`

## Security
- Device bearer token (rotated, grace period 24h)
- HMAC-SHA256 signed POSTs
- Rate limited (30/min)
- Tenant isolated (`institute_id` everywhere)
- Idempotent (SHA-256 hash)
- Audit-logged (all actions)

## Operations
See `docs/lab-analyzer-deployment.md`

## Testing
```bash
php artisan test --filter=LabIntegration
```
204+ tests covering parser, adapter, API, gateway, admin UI, chaos, load.

## Simulator
```bash
cd simulator
node src/index.js --list-scenarios
node src/index.js --scenario=valid_cbc --transport=tcp --port=5000
```
