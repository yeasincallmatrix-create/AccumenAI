# Lab Analyzer Integration — Production Deployment Guide

## Prerequisites

### Server (AccumenAI LIS)
- Laravel 12 running
- PHP 8.3+
- MariaDB/MySQL 8+
- Queue worker (Supervisor) running `queue:work`
- Reverb (optional, for real-time UI)

### Gateway PC (per lab site)
- Windows 10+ or Linux
- Node.js ≥18
- Network access to analyzer (serial/TCP)
- Outbound HTTPS to LIS server

## Server Deployment

### 1. Install Reverb (Optional)
```bash
composer require laravel/reverb
php artisan reverb:install
```

Add Reverb to Supervisor:

```ini
[program:reverb]
command=php /var/www/accumenai/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
```

Without Reverb the UI falls back to 30s polling (set `BROADCAST_CONNECTION=log`).

### 2. Run Migrations
```bash
php artisan migrate --force
```

### 3. Seed Permissions
```bash
php artisan db:seed --class=LabAnalyzerPermissionSeeder --force
```

### 4. Seed Vendor Parameter Maps (existing analyzers)
```bash
php artisan db:seed --class=SysmexXn550ParameterMapSeeder --force
php artisan db:seed --class=MindrayBc5150ParameterMapSeeder --force
```

### 5. Queue Worker
```ini
[program:accumenai-queue]
command=php /var/www/accumenai/artisan queue:work --tries=5 --timeout=90
autostart=true
autorestart=true
numprocs=2
```

## Gateway Deployment (per Lab PC)

### 1. Install
```bash
git clone <repo> /opt/accumenai-gateway
cd /opt/accumenai-gateway/gateway
npm install --production
```

### 2. Configure
```bash
cp .env.example .env
# Edit .env:
#  - LIS_BASE_URL=https://your-accumenai.com
#  - DEVICE_TOKEN=<from admin panel>
#  - ANALYZER_ID=<from admin panel>
#  - TRANSPORT=tcp (or serial/file)
#  - TCP_PORT=5000 (or serial: COM3)
```

### 3. Register Analyzer in LIS
1. Login to admin panel
2. Navigate to Medical → Laboratory → Analyzers
3. Create new analyzer (fill manufacturer/model/protocol)
4. Issue credential → copy plaintext token (shown once)
5. Paste token into gateway `.env`

### 4. Run as Service
Windows (nssm):
```cmd
nssm install AccumenGateway "C:\Program Files\nodejs\node.exe" "C:\accumenai-gateway\gateway\src\index.js"
nssm set AccumenGateway AppDirectory "C:\accumenai-gateway\gateway"
nssm start AccumenGateway
```

Linux (systemd):
```ini
[Unit]
Description=AccumenAI Lab Gateway
After=network.target

[Service]
Type=simple
User=lab
WorkingDirectory=/opt/accumenai-gateway/gateway
ExecStart=/usr/bin/node src/index.js
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

## Analyzer Configuration

### Sysmex XN-550
- Protocol: ASTM E1394
- Host: gateway PC IP
- Port: 5000
- Line ending: LF
- Sample ID field: O-record field 2

### Mindray BC-5150
- Protocol: ASTM E1394
- Host: gateway PC IP
- Port: 5000
- Line ending: CRLF or LF
- Sample ID field: O-record field 2

## Monitoring

### Server-side
- Dashboard: `/medical/laboratory/analyzers/dashboard`
- Message log: `/medical/laboratory/analyzers/{id}/messages`
- Failed messages: filter by status=dead

### Gateway-side
- Gateway logs to stdout (capture via service manager)
- Heartbeat: admin panel shows `last_seen_at`
- Queue depth: `sqlite3 gateway/data/outbox.sqlite "SELECT COUNT(*) FROM outbox WHERE status != 'acked'"`

## Troubleshooting

### Message not received
- Check gateway logs — is analyzer connecting?
- `telnet gateway-pc 5000` from another PC
- Check analyzer's IP/port config

### Message received but 401
- Token expired → rotate in admin panel
- Check `.env` DEVICE_TOKEN matches admin panel
- Within 24h of rotation the old token still works (grace period)

### Results quarantined (UNKNOWN_SAMPLE)
- Verify sample `accession_number` exists in LIS
- Check for typos in analyzer's sample ID field
- Resolve manually in message log UI

### Dead-letter after 5 retries
- Open message in log
- Check `error_code`
- Fix root cause (sample, mapping, etc.)
- Click "Retry"

## Backup & Recovery
- Server DB: daily backup (existing policy)
- Gateway outbox: `gateway/data/outbox.sqlite` — back up nightly
- Analyzer parameter maps: export from admin UI monthly
