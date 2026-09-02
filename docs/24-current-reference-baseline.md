# 24 - Current Reference Baseline

**Validated: 2 September 2026. Re-check before production because external services/policies change.**

## Technology

### Laravel 13
Laravel official changelog currently documents Laravel Framework 13.x updates in August 2026. Redis support is documented in Laravel 13.x; Redis queues are available in the 13.x API. Horizon is the monitoring/configuration layer for Redis-powered Laravel queues.

References:
- https://laravel.com/framework/docs/changelog
- https://laravel.com/framework/docs/redis
- https://api.laravel.com/docs/13.x/Illuminate/Queue/RedisQueue.html
- https://laravel.com/framework/docs/12.x/horizon (official Horizon docs currently surfaced with upgrade guidance)

## YouTube

YouTube's additional derived metrics policy explicitly lists Viewer Sentiment Analysis and gives NLP-on-comments sentiment/satisfaction as an allowed example under the accepted analytics use case. Accepted derived sentiment may be stored up to 36 months, while comment text and other data still follow the specified refresh/deletion policy.

References:
- https://developers.google.com/youtube/terms/derived-metrics-policy
- https://developers.google.com/youtube/terms/revision-history

## DiskusiWebHosting

Current forum statistics surfaced around 26,102 threads and 284,715 messages, with dedicated testimonial, complaint, VPS/cloud server, shared/cloud hosting, and ISP/network forums and current 2026 activity.

Reference:
- https://www.diskusiwebhosting.com/

## SerayaMotor

Review Corner currently shows ~1,278 topics and ~91.9k posts; Suggestion Corner ~3,188 topics and ~132k posts, with 2026 ownership/review activity.

References:
- https://www.serayamotor.com/diskusi/viewforum.php?f=19
- https://www.serayamotor.com/diskusi/viewforum.php?f=28
- https://www.serayamotor.com/diskusi/portal

## IndoForum

Terms updated 2 January 2025 state forum content belongs to posters and list operational forum rules. Use selective ingestion due noise/spam risk.

Reference:
- https://www.forum.or.id/help/terms/

## Bluesky / AT Protocol

Bluesky Protocol Services describes public infrastructure/open access; the firehose can be consumed without authentication and Jetstream is recommended for JSON streaming.

References:
- https://bsky.network/docs/protocol-services/
- https://bsky.network/docs/consuming-the-firehose/
- https://atproto.com/guides/streaming-data

## LowEndTalk

LowEndTalk remains active in 2026 with dedicated Reviews category and large hosting/VPS discussion corpus.

References:
- https://lowendtalk.com/
- https://lowendtalk.com/categories/reviews

## KASKUS

KASKUS remains an intended MVP adapter because of Indonesia-wide discussion coverage. Unlike sources above, the production adapter MUST treat current runtime preflight as authoritative: public-page availability, current robots directives, explicit automated-access restrictions found during implementation, and no bypass of login/CAPTCHA/access control. Do not encode assumptions about historical KASKUS policies into the core product.
