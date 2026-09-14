#!/bin/bash
# safe-dump.sh — Safe database dump that excludes user/auth/session tables
# Usage: bash scripts/safe-dump.sh
# Output: database/schema/full_data_safe.sql
set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# Detect mysqldump binary (XAMPP on Windows)
if [ -f "/c/xampp/mysql/bin/mysqldump.exe" ]; then
  MYSQLDUMP="/c/xampp/mysql/bin/mysqldump.exe"
elif [ -f "C:/xampp/mysql/bin/mysqldump.exe" ]; then
  MYSQLDUMP="C:/xampp/mysql/bin/mysqldump.exe"
else
  MYSQLDUMP="mysqldump"
fi

DB_NAME="${DB_DATABASE:-accumen_ai}"
DB_USER="${DB_USERNAME:-root}"
DB_PASS="${DB_PASSWORD:-}"
OUTPUT="database/schema/full_data_safe.sql"

# Tables to exclude (auth, sessions, logs, temp data)
EXCLUDE_TABLES=(
  users
  platform_admins
  platform_staffs
  institute_users
  institution_user
  sessions
  personal_access_tokens
  password_reset_tokens
  cache
  cache_locks
  jobs
  job_batches
  failed_jobs
  login_attempts
  email_otps
  phone_2fa_otps
  phone_password_reset_otps
  phone_verification_otps
  pending_registrations
  user_module_access
  institute_user_module_access
  platform_staff_permissions
  user_activity_logs
)

echo "=== Safe Database Dump ==="
echo "Database: $DB_NAME"
echo "Output:   $OUTPUT"
echo ""

# Build mysqldump command
CMD="$MYSQLDUMP"
if [ -n "$DB_PASS" ]; then
  CMD="$CMD -u $DB_USER -p'$DB_PASS'"
else
  CMD="$CMD -u $DB_USER"
fi

for table in "${EXCLUDE_TABLES[@]}"; do
  CMD="$CMD --ignore-table=$DB_NAME.$table"
done

CMD="$CMD $DB_NAME"

echo "Excluded tables:"
printf '  - %s\n' "${EXCLUDE_TABLES[@]}"
echo ""

# Run dump
echo "Dumping..."
eval "$CMD" > "$OUTPUT"

# Check result
if [ -f "$OUTPUT" ]; then
  SIZE=$(wc -c < "$OUTPUT" | xargs)
  LINES=$(wc -l < "$OUTPUT" | xargs)
  echo ""
  echo "Done! File: $OUTPUT"
  echo "  Size: $SIZE bytes"
  echo "  Lines: $LINES"
  echo ""
  echo "To import on server:"
  echo "  mysql -u USER -p DB_NAME < $OUTPUT"
else
  echo "ERROR: Dump failed"
  exit 1
fi
