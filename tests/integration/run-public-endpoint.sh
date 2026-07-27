#!/usr/bin/env bash

set -euo pipefail

CV="${CV:-cv}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
STATE_FILE="$(mktemp "${TMPDIR:-/tmp}/civiverify-public.XXXXXX")"

cleanup() {
  "$CV" php:script "$SCRIPT_DIR/PublicEndpoint.php" cleanup "$STATE_FILE" >/dev/null 2>&1 || true
}
trap cleanup EXIT

"$CV" php:script "$SCRIPT_DIR/PublicEndpoint.php" setup "$STATE_FILE"
"$CV" php:script "$SCRIPT_DIR/PublicEndpoint.php" test "$STATE_FILE"
