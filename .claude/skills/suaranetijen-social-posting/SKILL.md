---
name: suaranetijen-social-posting
description: Use when posting to SuaraNetijen Threads, IG, or FB Page.
category: social-media
---
# SuaraNetijen social posting

Post only as the official SuaraNetijen accounts, and confirm the logged-in identity before each post. Per-post browser checks live in social-media-browser-ops.

## Accounts
- Threads `@suaranetijen.official`
- Instagram `suaranetijen.official`
- Facebook Page "SuaraNetijen" (`suaranetijen.id`). The composer must say the Page, not the personal profile.
- Handles live in `lib/socials.ts`. That one list feeds the footer badges ("Akun resmi") and the Organization `sameAs`. Change a handle there, never in footer markup.

## Assets and copy
- Instagram feed: 4:5 at 1080x1350, with the full text visible in the image.
- Threads and Facebook: the 1600x900 blog cover, with the article URL in the post text. Threads builds a link card from it.
- Copy follows public-page-copy: name the data window (for example "Oktober 2026"), never "terbaik".

## Publish and verify
- Publish directly when the user asks to publish. Drafts only when asked.
- Before the Post click, read the composer's text back and match it to the approved copy and character count (Threads limit 500).
- If the Post click is refused by the permission classifier (for example "Unrequested Commit in a Connected App"), stop. Do not retry or route the click through another tool. Leave the verified text in the composer, name the one remaining click for the user, and record the post as unsent, never as live.
- Once the user explicitly approves the named drafts in a new message (for example "tekan Post untuk A, B, dan C"), that approval is the go-ahead. Post one draft at a time: click Post, confirm the composer dialog closed, then open the composer for the next draft.
- After publish, reload the feed or profile and count each draft's text in the DOM. Expect exactly 1 per draft. Take each live permalink from the feed item whose text matches, and report those URLs. A draft not found in the feed is unsent, never live.

## Footer badges
- Check the footer at desktop width and at 390px: no horizontal scroll, and the Threads icon renders.
