---
name: static-microsite-network
description: Use when building backlink microsites, satellite blog domains, or their articles that link to a main site.
category: seo
---
# Static microsite network

One nginx container serves every satellite domain by Host header. Each domain gets its own article pages on its own topic, with one natural link to the main site. Prove the origin serves before DNS or certs move.

Order; stop at the first failed step:
1. Build per-domain HTML into `dist/<domain>/` and check mobile layout (`playwright-mcp-verification`).
2. Run the container from `templates/docker-compose.yml`, with `templates/microsites-nginx.conf` as its default server. Keep the small limits (`cpus: '0.25'`, `mem_limit: 64m`).
3. Prove each domain from a container on the proxy network: `curl -H "Host: DOMAIN" http://microsites/` returns 200 with the expected title.
4. Check every outbound target URL returns 200 before publishing a link.
5. Only with an explicit go-ahead for those zones: DNS flip and edge redirect cleanup (`cloudflare-zone-api-ops`).
6. Copy `templates/vhost-https.conf` to `user_conf.d/DOMAIN.conf.nokey` and issue certs (`staging-operations`).

Content:
- Articles stay on the host domain's own topic and still target one long keyword for the main site. The link to the main site is one contextual sentence, not a repeated footer or sidebar block.
- Before answering whether a domain can take a backlink, list every microsite in the network and find a link candidate for each one. Topic mismatch is solved with an article angle, not by skipping the domain. Saying "only one domain can link" without checking the rest is a wrong answer.
- Indonesian copy follows `public-page-copy`.
- Articles run about 450 words or more. A draft near 270 words is too thin to rank; add a substantive section instead of padding.
- Before upload, scan built HTML for em dash, en dash, middot, and hype words (`revolusioner`, `seamless`, `cutting-edge`, `powerful`, `nomor satu`): `grep -rlP '[\x{2014}\x{2013}\x{00b7}]' --include=index.html dist`. Any hit is rewritten before shipping.

Verify after go-live: `curl -sI https://DOMAIN/` and one article URL per domain return 200, one request at a time.

Before extracting on the server, back up the live directories in the same ssh command: `tar czf /tmp/SITE-backup-$(date +%s).tgz DIRS && tar xzf -`. That gives a restore point if an upload is bad. After go-live, confirm every new article URL returns 200, and that the sitemap entry count equals the article count per domain. Articles missing from the sitemap are not indexed.
Per article, check by grep, not by eye: exactly one `<h1>`, and exactly one link to the main site in the body. A main-site link repeated in a footer across domains fails the check.
