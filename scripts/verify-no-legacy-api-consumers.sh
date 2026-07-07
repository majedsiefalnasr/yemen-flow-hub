#!/usr/bin/env bash
set -euo pipefail
LEGACY_PATTERNS=(
  "'/api/users"
  '"/api/users'
  "'/api/banks"
  '"/api/banks'
  "'/api/audit"
  '"/api/audit'
  "'/api/report-presets"
  '"/api/report-presets'
  "'/api/notifications"
  '"/api/notifications'
)
for pat in "${LEGACY_PATTERNS[@]}"; do
  if rg -n "$pat" frontend/app frontend/tests --glob '!**/*.md'; then
    echo "FAIL: legacy consumer found for pattern $pat"
    exit 1
  fi
done
echo "OK: no legacy API consumers in frontend/app"
