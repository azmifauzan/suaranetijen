# Staging redeploy runbook

Run after the container build and push pass their gates (see the container deploy safety skill). The compose directory for the staging app lives on the staging host. Read credentials from the host's `.env` at run time; never write them into this file, a log, or a commit.

## Build from the committed tree
`git archive HEAD | tar -x -C SCRATCH_DIR`, then run `docker build` inside that directory. Uncommitted edits stay out of the image. Name them in the report as not deployed, and do not claim a live feature that depends on them.

## Ordering
0. Hold the deploy while a background data batch (enrich, import) is still running. Recreating the app container kills the batch mid-run. Wait for its finished signal.
1. Workers, one host at a time by default. Find each host's compose directory from the container label `com.docker.compose.project.working_dir` rather than guessing. In that directory:
   - Pull the worker service and check the exit code. Do not pipe the pull through tail under `set -e`; the pipe hides a failed pull.
   - Confirm the compose `image:` line is `azmifauzan/suaranetijen:latest`. A pin to a tag missing from the registry makes the pull fail, and force-recreate then silently restarts the old local image.
   - Terminate Horizon in the container, wait until the container runs again, then `docker compose up -d --force-recreate --no-deps SERVICE`.
   - Run hosts in parallel only when the user asks for it. Each host still passes its own gate: pull exit 0, digest match, runtime version, Horizon running.
2. App, scheduler, and SSR: pull the app service, then `docker compose up -d --no-deps` for the app, scheduler, and SSR services. Add others only if their image changed.
   - `up -d` leaves a running container alone when its config is unchanged, even after pulling the same latest tag. A Running line can mean the old image still serves. Compare the container's image ID with the local image ID on the same host. Add force-recreate if they differ.
   - Never compare image IDs across hosts; they are host-local. Prove a cross-host pull with the repo digest of the image against the digest line that the push printed.
   - After any force-recreate, every service must report Up. A recreate can leave the app Dead and the scheduler or SSR Created (never started). Recovery: remove the dead containers with force, run `up -d` again, then reload the proxy and flush the page cache. The root cause of the Dead state is not known; check host disk and the Docker daemon if it recurs.
3. Migrate: run `php artisan migrate --force` inside the app container.
4. Reload the proxy (see the main skill).
5. After SSR reports healthy, flush the public page cache. A page requested during SSR startup is cached as a shell and served until flushed.
6. For a runtime or base-image bump (for example a PHP patch for a CVE), check the running version inside every container on every host, and in the built image before pushing. The image digest alone does not show the process changed.
7. Verify public (see the main skill).

## Access
Key auth to the staging host was denied in one session; password auth with credentials read from `.env` via a password helper worked. Ask the user for access if both fail. Never write credentials into a skill, command, log, or commit.
