#!/bin/sh
# Usage: acme-edge-probe.sh DOMAIN [DOMAIN...]
# Prints the HTTP status and redirect target of an ACME http-01 path per domain.
# Origin 404 or token body is fine. A 301 to another host means an edge redirect.
set -u
for domain in "$@"; do
  printf '%s ' "$domain"
  curl -s -m 10 -o /dev/null -w '%{http_code} %{redirect_url}\n' "http://$domain/.well-known/acme-challenge/probe"
done
