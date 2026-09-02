# 10 - Entity Matching & Opinion Filter

## Purpose

Sebelum sentiment, sistem harus menjawab dua pertanyaan saja:
1. Apakah teks ini merujuk ke entity tertentu?
2. Apakah teks ini mengandung opini/evaluasi tentang entity tersebut?

Ini data qualification, bukan fact checking.

## Entity matching stages

1. Normalize case, whitespace, punctuation.
2. Exact alias match.
3. Token/phrase match.
4. Context disambiguation untuk alias ambigu.
5. Optional LLM/model fallback hanya untuk ambiguous candidates.

## Parent/service rule

- `IDCloudHost bagus` -> entity IDCloudHost.
- `VPS IDCloudHost bagus` -> entity VPS IDCloudHost.
- Jangan otomatis menurunkan brand opinion ke service.

## Opinion relevance

Examples:

`Galaxy A57 kameranya keren` -> relevant opinion Samsung Galaxy A57.

`Saya makan Indomie sambil nonton Galaxy A57` -> mention saja, bukan opinion Indomie/Galaxy A57 kecuali ada evaluative statement.

`Ada yang pakai IDCloudHost?` -> neutral/non-opinion candidate; dapat diabaikan dari sentiment observation jika classifier tidak menemukan evaluasi.

## Sentiment-eligible output

```text
entity_id
relevant = true
sentiment_eligible = true
clean_text temporary
```

## Ambiguity

Jika entity tidak dapat dipastikan, masukkan `unmatched_mentions`/discard. Lebih baik kehilangan signal daripada memasukkan sentiment ke entity yang salah.
