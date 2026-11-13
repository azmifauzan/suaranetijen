#!/usr/bin/env bash
# Usage: verify-edge-cache.sh https://HOST /path [/path...]
# Probes one request at a time: plain (twice, to see MISS then HIT), cookie, X-Inertia.
set -euo pipefail
base="$1"
shift
for path in "$@"; do
  for variant in plain plain cookie inertia; do
    extra=()
    case "$variant" in
      cookie) extra=(-H 'Cookie: a=1') ;;
      inertia) extra=(-H 'X-Inertia: true') ;;
    esac
    headers=$(curl -s -D - -o /dev/null -m 20 "${extra[@]}" "$base$path" | tr -d '\r')
    status=$(printf '%s\n' "$headers" | grep -i '^cf-cache-status:' | awk '{print $2}' || true)
    age=$(printf '%s\n' "$headers" | grep -i '^age:' | awk '{print $2}' || true)
    echo "$variant $path cf=${status:-none} age=${age:--}"
    sleep 1
  done
done
