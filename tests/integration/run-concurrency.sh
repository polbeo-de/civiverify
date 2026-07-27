#!/usr/bin/env bash

set -euo pipefail

CV="${CV:-cv}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
STATE_FILE="$(mktemp "${TMPDIR:-/tmp}/civiverify-concurrency.XXXXXX")"
RESULT_DIR="$(mktemp -d "${TMPDIR:-/tmp}/civiverify-results.XXXXXX")"

cleanup() {
  "$CV" php:script "$SCRIPT_DIR/Concurrency.php" cleanup "$STATE_FILE" >/dev/null 2>&1 || true
  rm -rf "$RESULT_DIR"
}
trap cleanup EXIT

"$CV" php:script "$SCRIPT_DIR/Concurrency.php" setup "$STATE_FILE"
for worker in {1..8}; do
  "$CV" php:script "$SCRIPT_DIR/Concurrency.php" verify "$STATE_FILE" "$RESULT_DIR/$worker" &
done
wait
"$CV" php:script "$SCRIPT_DIR/Concurrency.php" check "$STATE_FILE" "$RESULT_DIR"
