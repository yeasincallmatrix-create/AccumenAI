# AccumenAI Lab Analyzer Simulator

Virtual lab analyzer for testing the AccumenAI LIS integration without physical hardware.

## Install

Zero dependencies — uses only Node.js ≥18.

```bash
cd simulator
# No install needed
```

## Quick Start

```bash
# List scenarios
node src/index.js --list-scenarios

# Send valid ASTM CBC over TCP (gateway must be listening on 127.0.0.1:5000)
node src/index.js --scenario=valid_cbc --transport=tcp --host=127.0.0.1 --port=5000

# Send HL7
node src/index.js --scenario=valid_hl7 --transport=tcp

# Send 10-message burst with 100ms delay
node src/index.js --scenario=batch_random_10 --count=10 --delay-ms=100

# Dump to file (for manual inspection)
node src/index.js --scenario=valid_cbc --transport=file --output=/tmp/cbc.astm

# Deterministic mode (CI-friendly)
node src/index.js --scenario=batch_random_10 --deterministic --seed=42
```

## Scenarios

| Name | Description |
|------|-------------|
| valid_cbc | Single Sysmex 5-part CBC (ASTM) |
| valid_cbc_3part | Mindray 3-part CBC (ASTM) |
| valid_hl7 | HL7 ORU^R01 5-part CBC |
| valid_csv | CSV with header |
| valid_raw_text | Raw text (semi-auto) |
| valid_retic | Sysmex retic mode (different panel) |
| valid_abnormal_hl7 | HL7 with H/L/HH/LL flags |
| malformed_hl7 | HL7 missing OBX values |
| malformed_astm_no_terminator | ASTM without L-record |
| malformed_csv_missing_value | CSV missing value cell |
| duplicate_burst | Same payload sent N times (dedup test) |
| slow_burst | N unique payloads with delay |
| batch_random_10 | 10 unique random CBCs |
| batch_random_100 | 100 unique random CBCs (load test) |
| asterisk_values | Sysmex **** error values |
| blank_values | Parameters with blank values |
| duplicate_with_whitespace | Same content, CRLF vs LF (dedup test) |

## Integration with Tests

The simulator is importable as a library:

```javascript
const { Simulator } = require('./simulator/src/simulator');
const sim = new Simulator({ scenario: 'valid_cbc', transport: 'stdout', fixturesDir: '...' });
const payloads = sim.buildPayloads();
```

Laravel E2E tests can spawn it as a child process:

```php
$proc = proc_open(
    'node ' . base_path('simulator/src/index.js') . ' --scenario=valid_cbc --transport=tcp --port=5599',
    ...
);
```

## Determinism

Pass `--deterministic --seed=42` for CI. Same seed → same payloads → same test results.

## Transports

- `tcp` — analyzer-style connection to gateway (default port 5000).
  Payloads are framed with EOT (`\x04`), matching the gateway TCP
  transport's terminator detection.
- `file` — write payload to disk (for inspection or file-transport gateway)
- `stdout` — print to console (for piping)
