# FlareSolverr field results (2026-11 snapshot)

Tested directly against the sidecar, 3+ attempts per source, before trusting the adapter.

- KASKUS: 3/3 solved. Responses said "Challenge not detected!": the block was the Next.js client-side render, not Cloudflare. A real browser executes it.
- SerayaMotor: 2/3 solved ("Challenge solved!"). The third timed out with "Error solving the challenge. Timeout after 60.0 seconds." Cloudflare, so a retry usually recovers it.
- IndoForum (forum.or.id): about 1 in 4 attempts got real content. Its own botguard script is not Cloudflare, so FlareSolverr often returns the unsolved interstitial with "Challenge not detected!". The rare success is a timing fluke, not a fix. A session flow with an explicit wait is the next step if volume is too low.

Re-test a source after any sidecar upgrade; these results are not guarantees.
