#!/bin/bash
# Phase 11 — Drop stale empty test databases left by --parallel runs.
#
# Usage:
#   scripts/drop-stale-test-dbs.sh          # preview (dry-run)
#   scripts/drop-stale-test-dbs.sh --apply   # actually drop
#
# These databases are created by Laravel's --parallel flag and
# are never cleaned up automatically. Safe to drop at any time.
#
# SAFETY: Only drops databases matching monetix_test_test_* pattern.
# Never touches monetix_test or any production database.

set -euo pipefail

MYSQL="mysql -u root"
PATTERN="monetix_test_test_%"
APPLY=false

if [[ "${1:-}" == "--apply" ]]; then
    APPLY=true
fi

echo "=== Stale test DB cleanup ==="
echo "Pattern: $PATTERN"
echo ""

# List matching databases
STALE=$($MYSQL -N -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE '$PATTERN';" 2>/dev/null || true)

if [[ -z "$STALE" ]]; then
    echo "No stale databases found."
    exit 0
fi

echo "Found stale databases:"
echo "$STALE"
echo ""

if [[ "$APPLY" == "true" ]]; then
    echo "Dropping..."
    while IFS= read -r db; do
        $MYSQL -e "DROP DATABASE \`$db\`;"
        echo "  Dropped: $db"
    done <<< "$STALE"
    echo "Done."
else
    echo "Dry-run mode. To apply: scripts/drop-stale-test-dbs.sh --apply"
fi
