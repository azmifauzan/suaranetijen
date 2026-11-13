---
name: shared-nginx-cert-swap
description: Use when nginx wildcard cert swap or certbot renew fails.
category: deployment
---
# Shared nginx cert swap

A wildcard cert needs DNS-01. A renewal conf still on `authenticator = webroot` for a wildcard name fails at its next renewal while the old cert keeps serving, so the failure stays hidden until expiry. Issue the new cert first, swap the vhosts, then retire the old renewal conf. Issuance steps live in `letsencrypt-wildcard-dns01`.

Stop at the first failed step:
1. Read every renewal conf's `authenticator`, and list each vhost `ssl_certificate` path that names the old cert.
2. Check the new cert on disk: `openssl x509 -noout -ext subjectAltName -enddate`.
3. Back up the vhost conf with a timestamp suffix, using sudo. The nginx config dir is root-owned; plain `cp` fails with Permission denied.
4. `sed` the old `live/` paths to the new cert directory, then run `nginx -t`. If the test fails, restore the backup and stop. Reload only after the test passes.
5. Rename the old renewal conf to `.disabled-TIMESTAMP` so renewal stops picking it up.
6. Verify the origin by IP with SNI: `openssl s_client -connect ORIGIN_IP:443 -servername HOST`. A proxied name shows the edge certificate, a separate cert, so a stale subject there does not mean the origin is wrong. Compare subject and notAfter, not only the SAN list.
7. Check every tenant host the same way, and curl one login URL per host for 200.

Long commands: `certbot renew --dry-run` runs past the 120s tool timeout. Run it in background and wait on its output file with a Monitor until-loop. Chained `sleep N; cat` is blocked by the harness.

Scratch scripts in the session scratchpad can disappear between turns. Confirm the file exists before running it, and re-create it if not.