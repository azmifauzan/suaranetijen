Prettier bukan formatter proyek ini (proyek pakai `vp check`), jadi ia ikut mengubah baris lain. Kembalikan dan terapkan ulang perubahan saya saja.

tool_result: "resources/js/pages/Comparison/Show.vue | 85 +++++++++++++++++++++++++++++++++-\n resources/js/pages/Entities/Show.vue   | 24 ++++++++++"  (after prettier --write showed 111 changed lines on Comparison/Show.vue)

package.json: "check": "vp check", "check:fix": "vp check --fix"

npx vp --help: "fmt, format    Format code" / "check    Run format, lint, and type checks"

npx eslint: "If you still have problems after following the migration guide, please stop by https://eslint.org/chat/help"