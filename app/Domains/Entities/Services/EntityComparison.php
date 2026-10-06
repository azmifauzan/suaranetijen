<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Services\TopThemesService;
use Illuminate\Support\Str;

/**
 * Side-by-side view of two entities' Sentimen Netijen (docs/31 Fase 3, ADR-012). It shows each entity's
 * own numbers next to the other and never combines them into one score, a winner, or a per-theme score.
 */
class EntityComparison
{
    private const MIN_GAP = 5;

    public function __construct(
        protected EntityOgImage $snapshots,
        protected TopThemesService $themes,
    ) {}

    /**
     * Canonical URL segment for two slugs: alphabetical, joined by "-vs-".
     */
    public function canonicalPair(string $slugA, string $slugB): string
    {
        $slugs = [$slugA, $slugB];
        sort($slugs);

        return implode('-vs-', $slugs);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function parse(string $pair): ?array
    {
        $parts = explode('-vs-', $pair);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || $parts[0] === $parts[1]) {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    public function isCurated(string $canonicalPair): bool
    {
        return in_array($canonicalPair, (array) config('comparisons.pairs', []), true);
    }

    /**
     * Both sides of a pair, or null when either entity is missing, thin, or the two are not in the same category.
     *
     * @return array{sides: array<int, array<string, mixed>>, verdict: string, same_category: bool}|null
     */
    public function build(Entity $a, Entity $b): ?array
    {
        $sides = [];

        foreach ([$a, $b] as $entity) {
            $snapshot = $this->snapshots->eligibleSnapshot($entity);

            if ($snapshot === null) {
                return null;
            }

            $sides[] = $this->side($entity, $snapshot);
        }

        return [
            'sides' => $sides,
            'verdict' => $this->verdict($sides[0], $sides[1]),
            'same_category' => $a->category_id === $b->category_id,
        ];
    }

    /**
     * Curated comparisons that involve this entity and whose other side is publicly eligible, newest data first.
     *
     * @return array<int, array{pair: string, label: string}>
     */
    public function forEntity(Entity $entity, int $limit = 6): array
    {
        $links = [];

        foreach ((array) config('comparisons.pairs', []) as $pair) {
            $slugs = $this->parse($pair);

            if ($slugs === null || ! in_array($entity->slug, $slugs, true)) {
                continue;
            }

            $otherSlug = $slugs[0] === $entity->slug ? $slugs[1] : $slugs[0];
            $other = Entity::query()->active()->where('searchable', true)->where('slug', $otherSlug)->first();

            if ($other === null || $other->category_id !== $entity->category_id || $this->snapshots->eligibleSnapshot($other) === null) {
                continue;
            }

            $links[] = ['pair' => $pair, 'label' => "{$entity->name} vs {$other->name}"];

            if (count($links) >= $limit) {
                break;
            }
        }

        return $links;
    }

    /**
     * Curated pairs whose two entities are in the same category and both clear the public threshold:
     * the set that belongs in the sitemap. lastmod is the later of the two snapshot calculation times.
     *
     * @return array<int, array{pair: string, lastmod: string}>
     */
    public function indexablePages(): array
    {
        $pairs = (array) config('comparisons.pairs', []);
        $slugs = collect($pairs)->flatMap(fn (string $pair): array => $this->parse($pair) ?? [])->unique()->values();

        $entities = Entity::query()->active()->where('searchable', true)->whereIn('slug', $slugs)->get()->keyBy('slug');
        $snapshots = $entities->map(fn (Entity $entity): ?SentimentSnapshot => $this->snapshots->eligibleSnapshot($entity));

        $pages = [];
        foreach ($pairs as $pair) {
            $parts = $this->parse($pair);

            if ($parts === null || ! $entities->has($parts[0]) || ! $entities->has($parts[1])) {
                continue;
            }

            if ($entities[$parts[0]]->category_id !== $entities[$parts[1]]->category_id) {
                continue;
            }

            $first = $snapshots[$parts[0]];
            $second = $snapshots[$parts[1]];

            if ($first === null || $second === null) {
                continue;
            }

            $pages[] = ['pair' => $pair, 'lastmod' => date('c', max($first->calculated_at->getTimestamp(), $second->calculated_at->getTimestamp()))];
        }

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    protected function side(Entity $entity, SentimentSnapshot $snapshot): array
    {
        $total = max(1, (int) $snapshot->positive_count + (int) $snapshot->neutral_count + (int) $snapshot->negative_count);
        $themes = $this->themes->getTopThemesForEntity($entity, $snapshot->period);

        return [
            'name' => $entity->name,
            'slug' => $entity->slug,
            'type_label' => $entity->type->label(),
            'category' => $entity->category->name,
            'category_slug' => $entity->category->slug,
            'score' => round((float) $snapshot->score, 1),
            'opinion_count' => (int) $snapshot->opinion_count,
            'distribution' => [
                'positive_pct' => round((int) $snapshot->positive_count / $total * 100, 1),
                'neutral_pct' => round((int) $snapshot->neutral_count / $total * 100, 1),
                'negative_pct' => round((int) $snapshot->negative_count / $total * 100, 1),
            ],
            'positive_themes' => array_slice($themes['positive_themes'], 0, 3),
            'negative_themes' => array_slice($themes['negative_themes'], 0, 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    protected function verdict(array $a, array $b): string
    {
        $gap = abs($a['score'] - $b['score']);
        [$high, $low] = $a['score'] >= $b['score'] ? [$a, $b] : [$b, $a];

        if ($gap < self::MIN_GAP) {
            return sprintf(
                'Sentimen netizen untuk %s (%s/100) dan %s (%s/100) relatif setara.',
                $a['name'],
                $this->number($a['score']),
                $b['name'],
                $this->number($b['score']),
            );
        }

        return sprintf(
            'Sentimen netizen untuk %s (%s/100) lebih tinggi daripada %s (%s/100).',
            $high['name'],
            $this->number($high['score']),
            $low['name'],
            $this->number($low['score']),
        );
    }

    /**
     * Title, description, FAQ and JSON-LD for the comparison page. Wording follows how people search
     * ("bagus mana") without claiming a best; the verdict only restates the two scores.
     *
     * @param  array{sides: array<int, array<string, mixed>>, verdict: string, same_category: bool}  $comparison
     * @return array{title: string, meta_description: string, faq: array<int, array{question: string, answer: string}>, breadcrumb_json_ld: array<string, mixed>, faq_json_ld: array<string, mixed>}
     */
    public function seo(array $comparison, string $pair): array
    {
        [$a, $b] = $comparison['sides'];
        $baseUrl = rtrim((string) config('app.url'), '/');

        $title = "{$a['name']} vs {$b['name']}: Bagus Mana Menurut Netizen?";
        if (mb_strlen($title) > 62) {
            $title = "{$a['name']} vs {$b['name']}: Sentimen Netizen";
        }

        $description = sprintf(
            'Bandingkan sentimen netizen: %s %s/100 dari %s opini, %s %s/100 dari %s opini. Lihat tema yang sering dipuji dan dikeluhkan.',
            $a['name'],
            $this->number($a['score']),
            number_format($a['opinion_count'], 0, ',', '.'),
            $b['name'],
            $this->number($b['score']),
            number_format($b['opinion_count'], 0, ',', '.'),
        );

        $faq = [
            [
                'question' => "{$a['name']} atau {$b['name']}, mana yang lebih disukai netizen?",
                'answer' => $comparison['verdict'].' Skor Sentimen Netijen menunjukkan sebaran opini publik, bukan penilaian kualitas produk.',
            ],
            [
                'question' => "Apa keluhan yang paling sering muncul tentang {$a['name']} dan {$b['name']}?",
                'answer' => $this->complaintAnswer($a).' '.$this->complaintAnswer($b),
            ],
            [
                'question' => 'Bagaimana perbandingan ini dihitung?',
                'answer' => 'Tiap entitas dihitung sendiri dari opini publik 365 hari terakhir (atau seluruh waktu bila data belum cukup), minimal 30 opini. Kedua skor ditampilkan berdampingan tanpa digabung, dan sponsor tidak memengaruhi skor.',
            ],
        ];

        return [
            'title' => $title,
            'meta_description' => $description,
            'faq' => $faq,
            'breadcrumb_json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => "{$baseUrl}/"],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $a['category'], 'item' => "{$baseUrl}/category/".$a['category_slug']],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => "{$a['name']} vs {$b['name']}", 'item' => "{$baseUrl}/banding/{$pair}"],
                ],
            ],
            'faq_json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                ], $faq),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $side
     */
    protected function complaintAnswer(array $side): string
    {
        if ($side['negative_themes'] === []) {
            return "Belum ada keluhan berulang yang dominan untuk {$side['name']}.";
        }

        $labels = array_map(
            fn (array $theme): string => "{$theme['display_label']} (disebut {$theme['observation_count']} kali)",
            $side['negative_themes'],
        );

        return "Untuk {$side['name']}: ".implode(', ', $labels).'.';
    }

    protected function number(float $value): string
    {
        return Str::of((string) round($value))->toString();
    }
}
