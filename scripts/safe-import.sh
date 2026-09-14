#!/bin/bash
# safe-import.sh — Import safe dump on server (skips user tables)
# Usage: bash scripts/safe-import.sh /path/to/full_data_safe.sql
# Run from server SSH
set -e

DUMP_FILE="${1:-full_data_safe.sql}"
DB_USER="${DB_USER:-accumena_user2}"
DB_PASS="${DB_PASS:-YaAccu123!}"
DB_NAME="${DB_NAME:-accumena_accumenaI}"

if [ ! -f "$DUMP_FILE" ]; then
  echo "ERROR: File not found: $DUMP_FILE"
  echo "Usage: bash safe-import.sh /path/to/full_data_safe.sql"
  exit 1
fi

echo "=== Safe Database Import ==="
echo "File:     $DUMP_FILE"
echo "Database: $DB_NAME"
echo "User:     $DB_USER"
echo ""
echo "This will overwrite schema + data for ALL tables EXCEPT:"
echo "  users, platform_admins, sessions, auth tokens, logs"
echo ""

# Import
echo "Importing..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$DUMP_FILE"

echo ""
echo "Done! Now run:"
echo "  php artisan db:seed --class=ModuleRegistrySeeder"
echo "  php artisan config:cache && php artisan route:cache && php artisan view:cache"
