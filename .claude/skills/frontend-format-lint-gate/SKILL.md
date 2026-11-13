---
name: frontend-format-lint-gate
description: Use when formatting or linting Vue/TS files with vp check.
category: frontend
---

# Frontend format and lint gate

Use the repo's own toolchain for JS and Vue: `npx vp check` to verify, `npx vp check --fix` to fix. Do not run `npx prettier --write` or `npx eslint` here. Prettier rewrites quotes and trailing commas across the whole file, and the eslint run has no working config for these files.

## Rules

- Read the `check` and `check:fix` scripts in `package.json` before choosing a formatter. Use the commands the repo already declares.
- Before a formatter run or a `git checkout` on any file with uncommitted work, save `git diff -- <file>` for every touched file to scratch. Re-apply your edits from that diff, then confirm with `git diff --stat`.
- A formatter that turns a two-line logic change into a 100-plus-line diff is wrong. Revert it before going further.
- Type check with `npx vue-tsc --noEmit` alongside `npx vp check`.
