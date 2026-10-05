#!/usr/bin/env bash
# FTS Corporate AI — restore a backup into a STAGING or TESTING database only
# (PLAN_08). The target must be named explicitly and end with _staging or
# _test; production databases are refused.
#
# Usage:
#   RESTORE_TARGET_DB=corporate_ai_staging deploy/scripts/restore-staging.sh /var/backups/corporate-ai/corporate_ai-20261005T011500Z.dump
#
# Credentials come from PGHOST/PGPORT/PGUSER and PGPASSFILE (0600) or peer
# authentication, never from the command line.
set -euo pipefail

dump="${1:?Usage: RESTORE_TARGET_DB=<name>_staging $0 <file.dump>}"
target="${RESTORE_TARGET_DB:?Set RESTORE_TARGET_DB explicitly}"

case "$target" in
    *_staging|*_test) ;;
    *) echo "Refusing to restore into '$target': name must end with _staging or _test." >&2; exit 1 ;;
esac

if [[ -n "${PGPASSWORD:-}" ]]; then
    echo "Refusing to run with PGPASSWORD set; use PGPASSFILE or peer authentication." >&2
    exit 1
fi

if [[ -f "$dump.sha256" ]]; then
    (cd "$(dirname "$dump")" && sha256sum --check --status "$(basename "$dump").sha256") \
        || { echo "Checksum mismatch for $dump" >&2; exit 1; }
fi

echo "Restoring $(basename "$dump") into $target (existing objects are replaced)…"
pg_restore --clean --if-exists --no-owner --no-privileges --single-transaction --dbname="$target" "$dump"
echo "Restore finished. Next: php artisan migrate --force against $target, then repeat the staging journey (docs/OPERATIONS.md)."
