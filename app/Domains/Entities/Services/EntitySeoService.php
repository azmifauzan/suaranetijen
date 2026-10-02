<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Models\Entity;

class EntitySeoService
{
    /**
     * Generate SEO metadata for an entity detail page (docs/31 Fase 1).
     *
     * @param  array{is_eligible: bool, score: float|int|null, opinion_count: int, distribution?: array{positive_pct: float, neutral_pct: float, negative_pct: float}|null}  $sentiment
     * @param  array{positive_themes?: array<int, array{display_label: string, observation_count: int}>, negative_themes?: array<int, array{display_label: string, observation_count: int}>}  $themes
     * @param  array{rating_count: int, rating_average: float|null}  $rating
     * @return array{
     *     title: string,
     *     meta_description: string,
     *     intent_subtitle: string,
     *     faq: array<int, array{question: string, answer: string}>,
     *     breadcrumb_json_ld: array<string, mixed>,
     *     faq_json_ld: array<string, mixed>|null
     * }
     */
    public function generate(
        Entity $entity,
        array $sentiment,
        array $themes = [],
        array $rating = ['rating_count' => 0, 'rating_average' => null]
    ): array {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $name = $entity->name;
        $isEligible = $sentiment['is_eligible'];
        $opinionCount = $sentiment['opinion_count'];
        $score = $sentiment['score'] !== null ? round((float) $sentiment['score']) : null;

        $posPct = $sentiment['distribution']['positive_pct'] ?? 0;
        $neuPct = $sentiment['distribution']['neutral_pct'] ?? 0;
        $negPct = $sentiment['distribution']['negative_pct'] ?? 0;

        $positiveThemes = $themes['positive_themes'] ?? [];
        $negativeThemes = $themes['negative_themes'] ?? [];

        // 1. Title generation (target <= 60 chars)
        $title = $this->buildTitle($name, $isEligible, $opinionCount, $score);

        // 2. Meta description (deterministic, data-driven, max ~160 chars)
        $metaDescription = $this->buildMetaDescription($name, $isEligible, $opinionCount, $posPct, $negPct, $positiveThemes, $negativeThemes);

        // 3. Subtitle intent statement
        $intentSubtitle = $isEligible
            ? "Bagus atau tidak menurut netizen? Ringkasan dari {$opinionCount} opini publik."
            : 'Opini netizen dan indeks sentimen publik.';

        // 4. FAQ generation (3-5 questions)
        $faq = $this->buildFaq($entity, $isEligible, $opinionCount, $score, $posPct, $neuPct, $negPct, $positiveThemes, $negativeThemes, $rating);

        // 5. Breadcrumb JSON-LD
        $breadcrumbs = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Beranda',
                'item' => "{$baseUrl}/",
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $entity->category->name,
                'item' => "{$baseUrl}/category/{$entity->category->slug}",
            ],
        ];

        $pos = 3;
        if ($entity->parent) {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $entity->parent->name,
                'item' => "{$baseUrl}/e/{$entity->parent->slug}",
            ];
        }

        $breadcrumbs[] = [
            '@type' => 'ListItem',
            'position' => $pos,
            'name' => $name,
            'item' => "{$baseUrl}/e/{$entity->slug}",
        ];

        $breadcrumbJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        ];

        // 6. FAQ JSON-LD
        $faqJsonLd = ! empty($faq) ? [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ], $faq),
        ] : null;

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'intent_subtitle' => $intentSubtitle,
            'faq' => $faq,
            'breadcrumb_json_ld' => $breadcrumbJsonLd,
            'faq_json_ld' => $faqJsonLd,
        ];
    }

    /**
     * Build SEO title fitting search intent and character budget (<= 60 chars).
     */
    protected function buildTitle(string $name, bool $isEligible, int $opinionCount, ?float $score): string
    {
        if (! $isEligible || $score === null) {
            return "{$name}: Sentimen dan Opini Netizen";
        }

        $formattedCount = number_format($opinionCount, 0, ',', '.');

        // Option 1: "Review {Nama}: Bagus atau Tidak Menurut {n} Opini Netizen"
        $opt1 = "Review {$name}: Bagus atau Tidak Menurut {$formattedCount} Opini Netizen";
        if (mb_strlen($opt1) <= 60) {
            return $opt1;
        }

        // Option 2: "Review {Nama} Menurut Netizen: Sentimen {skor}/100"
        $opt2 = "Review {$name} Menurut Netizen: Sentimen {$score}/100";
        if (mb_strlen($opt2) <= 60) {
            return $opt2;
        }

        // Option 3: "Review {Nama}: Sentimen Netizen {skor}/100"
        $opt3 = "Review {$name}: Sentimen Netizen {$score}/100";
        if (mb_strlen($opt3) <= 60) {
            return $opt3;
        }

        // Option 4: "Review {Nama}: Sentimen {skor}/100"
        $opt4 = "Review {$name}: Sentimen {$score}/100";
        if (mb_strlen($opt4) <= 60) {
            return $opt4;
        }

        return "Review {$name} di SuaraNetijen";
    }

    /**
     * Build deterministic meta description from sentiment and theme data.
     *
     * @param  array<int, array{display_label: string, observation_count: int}>  $positiveThemes
     * @param  array<int, array{display_label: string, observation_count: int}>  $negativeThemes
     */
    protected function buildMetaDescription(
        string $name,
        bool $isEligible,
        int $opinionCount,
        float $posPct,
        float $negPct,
        array $positiveThemes,
        array $negativeThemes
    ): string {
        if (! $isEligible) {
            return "Indeks sentimen dan opini netizen untuk {$name} di SuaraNetijen.";
        }

        $formattedCount = number_format($opinionCount, 0, ',', '.');
        $desc = "{$formattedCount} opini netizen tentang {$name}: {$posPct}% positif, {$negPct}% negatif.";

        $topPos = $this->firstPublishableLabel($positiveThemes);
        $topNeg = $this->firstPublishableLabel($negativeThemes);

        if ($topPos !== null) {
            $addition = " Paling sering dipuji: {$topPos}.";
            if (mb_strlen($desc.$addition) <= 160) {
                $desc .= $addition;
            }
        }

        if ($topNeg !== null) {
            $addition = " Paling sering dikeluhkan: {$topNeg}.";
            if (mb_strlen($desc.$addition) <= 160) {
                $desc .= $addition;
            }
        }

        return $desc;
    }

    /**
     * Build deterministic FAQ items from data.
     *
     * @param  array<int, array{display_label: string, observation_count: int}>  $positiveThemes
     * @param  array<int, array{display_label: string, observation_count: int}>  $negativeThemes
     * @param  array{rating_count: int, rating_average: float|null}  $rating
     * @return array<int, array{question: string, answer: string}>
     */
    protected function buildFaq(
        Entity $entity,
        bool $isEligible,
        int $opinionCount,
        ?float $score,
        float $posPct,
        float $neuPct,
        float $negPct,
        array $positiveThemes,
        array $negativeThemes,
        array $rating
    ): array {
        $name = $entity->name;
        $faq = [];

        // Q1: Apakah X bagus menurut netizen?
        $q1 = "Apakah {$name} bagus menurut netizen?";
        if ($isEligible && $score !== null) {
            $formattedCount = number_format($opinionCount, 0, ',', '.');
            $a1 = "Berdasarkan analisis {$formattedCount} opini publik di SuaraNetijen, {$name} memiliki skor sentimen {$score}/100 dengan {$posPct}% sentimen positif, {$neuPct}% netral, dan {$negPct}% negatif.";
        } else {
            $a1 = "Saat ini SuaraNetijen belum mengumpulkan minimal 30 opini publik yang dibutuhkan untuk menghitung skor sentimen publik {$name}.";
        }
        $faq[] = ['question' => $q1, 'answer' => $a1];

        // Q2: Apa keluhan paling sering tentang X?
        $q2 = "Apa keluhan paling sering tentang {$name}?";
        if (! empty($negativeThemes)) {
            $items = array_slice($negativeThemes, 0, 3);
            $labels = array_map(fn ($t) => "{$t['display_label']} ({$t['observation_count']} opini)", $items);
            $a2 = "Keluhan netizen yang paling sering muncul tentang {$name} adalah seputar ".implode(', ', $labels).'.';
        } else {
            $a2 = "Belum tercatat keluhan dominan dalam agregat opini publik netizen untuk {$name} pada periode ini.";
        }
        $faq[] = ['question' => $q2, 'answer' => $a2];

        // Q3: Apa kelebihan X yang paling sering dipuji?
        $q3 = "Apa kelebihan {$name} menurut netizen?";
        if (! empty($positiveThemes)) {
            $items = array_slice($positiveThemes, 0, 3);
            $labels = array_map(fn ($t) => "{$t['display_label']} ({$t['observation_count']} opini)", $items);
            $a3 = "Hal yang paling sering dipuji netizen mengenai {$name} antara lain seputar ".implode(', ', $labels).'.';
        } else {
            $a3 = 'Pujian spesifik belum mencapai frekuensi tema dominan pada periode ini.';
        }
        $faq[] = ['question' => $q3, 'answer' => $a3];

        // Q4: Dari mana data ini berasal?
        $faq[] = [
            'question' => "Dari mana data sentimen netizen tentang {$name} berasal?",
            'answer' => 'Data dihimpun secara otomatis dari berbagai forum publik, media sosial, dan portal ulasan daring di Indonesia, lalu diklasifikasi dan diagregasikan. Sponsor tidak memengaruhi skor, dan tiap sumber diperlakukan sama.',
        ];

        // Q5: Rating pengguna jika ada
        if ($rating['rating_count'] > 0 && $rating['rating_average'] !== null) {
            $faq[] = [
                'question' => "Berapa rating netizen untuk {$name} di SuaraNetijen?",
                'answer' => "Rating pengguna untuk {$name} adalah {$rating['rating_average']}/5 dari total {$rating['rating_count']} rating pengguna langsung di SuaraNetijen.",
            ];
        }

        return $faq;
    }

    /**
     * First theme label that may appear in a meta description. Labels with a superlative
     * ("Samsung terbaik") are skipped, not stripped: stripping leaves a meaningless fragment.
     *
     * @param  array<int, array{display_label: string, observation_count: int}>  $themes
     */
    protected function firstPublishableLabel(array $themes): ?string
    {
        foreach ($themes as $theme) {
            $label = trim($theme['display_label']);

            if ($label !== '' && ! preg_match('/\b(terbaik|terburuk)\b/iu', $label)) {
                return $label;
            }
        }

        return null;
    }
}
