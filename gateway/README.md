# AccumenAI Lab Gateway

Local gateway connecting on-premise lab analyzers to the AccumenAI LIS.

## Install

```bash
cd gateway
npm install
cp .env.example .env
# Edit .env with your DEVICE_TOKEN + ANALYZER_ID
```

## Run

```bash
npm start
```

## Transports

- `tcp` — analyzer connects to gateway via TCP (default port 5000)
- `serial` — RS-232 via COM port
- `file` — watch a directory for dropped files

## Offline Mode

Results are queued in SQLite (`data/outbox.sqlite`). If the network is
down, results are retried every 10 seconds. No data is lost.

## Device Credentials

Obtain the `DEVICE_TOKEN` from the AccumenAI admin panel:

1. Navigate to Medical → Laboratory → Analyzers
2. Select your analyzer
3. Click Regenerate Token
4. Copy the token into `.env`

## Health

The gateway sends a heartbeat every 60 seconds.
Check analyzer status in the AccumenAI admin panel.

## Security

- All results are HMAC-SHA256 signed
- Bearer token authentication
- TLS required (use an `https://` URL)
- Gateway logs never include patient PII — message IDs and accession
  numbers only
