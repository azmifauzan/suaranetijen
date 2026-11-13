---
name: user-submission-moderation
description: Use when user submissions feed an admin review queue.
category: security
---
# User submission moderation

A public or logged-in submission form must never disclose hidden records and must never let submitter text pose as system-owned data.

- Duplicate-name checks match only public, active records. A hit on a hidden, disabled, or pending entity returns a generic message (for example "sudah terdaftar atau sedang ditinjau") with no name, slug, or URL.
- Submitter text (description, category, parent, reasoning) stays on the submission row. A queue row created from a submission must not carry user-chosen category, parent, or reasoning as its authoritative suggestion fields; admins read those from the submission.
- Shared dedupe keys: a user submission may land on a queue row keyed by a shared term, but only scanner-enriched fields are authoritative on that row.
- Feature tests pin both: the validation error for a hidden name must lack the hidden slug, and a submission carrying a description must leave the candidate's `reasoning` null.
- Reuse the model's `active()` scope for visibility rather than re-deriving status checks in the controller.