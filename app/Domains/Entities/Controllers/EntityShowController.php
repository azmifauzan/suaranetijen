<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentDaily;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Domains\Themes\Services\TopThemesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntityShowController extends Controller
{
    public function __construct(
        protected TopThemesService $topThemesService
    ) {}

    /**
     * Display the specified entity public page.
     */
    public function show(string $slug, Request $request): Response
    {
        /** @var Entity $entity */
        $entity = Entity::query()
            ->with(['category', 'parent', 'aliases', 'ratingSnapshot', 'smartphoneSpec', 'carSpec', 'motorcycleSpec', 'personProfile'])
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        // Period resolution: query param or 365d default, fallback to 'all' if 365d empty
        $requestedPeriodStr = $request->query('period');
        $snapshots = SentimentSnapshot::query()
            ->where('entity_id', $entity->id)
            ->get()
            ->keyBy(fn (SentimentSnapshot $s) => $s->period->value);

        $selectedPeriod = Period::OneYear;
        if ($requestedPeriodStr && ($p = Period::tryFrom($requestedPeriodStr))) {
            $selectedPeriod = $p;
        } elseif (! $snapshots->has(Period::OneYear->value) && $snapshots->has(Period::All->value)) {
            $selectedPeriod = Period::All;
        }

        $activeSnapshot = $snapshots->get($selectedPeriod->value) ?? $snapshots->get(Period::All->value);

        $sentimentData = null;
        $opinionCount = $activeSnapshot ? (int) $activeSnapshot->opinion_count : 0;
        $isPublicScoreEligible = ScoreCalculator::isPublicScoreEligible($opinionCount);

        if ($activeSnapshot && $isPublicScoreEligible && $activeSnapshot->score !== null) {
            $pos = (int) $activeSnapshot->positive_count;
            $neu = (int) $activeSnapshot->neutral_count;
            $neg = (int) $activeSnapshot->negative_count;
            $total = max(1, $pos + $neu + $neg);

            $sentimentData = [
                'is_eligible' => true,
                'score' => (float) $activeSnapshot->score,
                'opinion_count' => $opinionCount,
                'positive_count' => $pos,
                'neutral_count' => $neu,
                'negative_count' => $neg,
                'distribution' => [
                    'positive_pct' => round(($pos / $total) * 100, 1),
                    'neutral_pct' => round(($neu / $total) * 100, 1),
                    'negative_pct' => round(($neg / $total) * 100, 1),
                ],
                'model_version' => $activeSnapshot->sentiment_model_version,
                'formula_version' => $activeSnapshot->score_formula_version,
            ];
        } else {
            $sentimentData = [
                'is_eligible' => false,
                'score' => null,
                'opinion_count' => $opinionCount,
                'positive_count' => $activeSnapshot ? (int) $activeSnapshot->positive_count : 0,
                'neutral_count' => $activeSnapshot ? (int) $activeSnapshot->neutral_count : 0,
                'negative_count' => $activeSnapshot ? (int) $activeSnapshot->negative_count : 0,
                'distribution' => null,
                'empty_state_message' => 'Crawler opini publik belum mengumpulkan minimal 30 opini netijen untuk entitas ini. Skor agregat publik akan dihitung otomatis saat pipeline observasi aktif.',
            ];
        }

        // Top Suara Netijen (Theme Index per docs/25)
        $themesData = $this->topThemesService->getTopThemesForEntity($entity, $selectedPeriod);

        // Related entities in the same category
        $relatedEntities = Entity::query()
            ->where('category_id', $entity->category_id)
            ->where('id', '!=', $entity->id)
            ->active()
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name', 'slug', 'type']);

        $userRating = $request->user()
            ? UserRating::query()
                ->whereBelongsTo($entity)
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->value('rating')
            : null;

        // Daily sentiment trend (up to 30 days)
        $trend = SentimentDaily::query()
            ->where('entity_id', $entity->id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (SentimentDaily $d) => [
                'date' => $d->date->format('Y-m-d'),
                'label' => $d->date->format('d M'),
                'score' => $d->score !== null ? (float) $d->score : null,
                'opinion_count' => (int) $d->opinion_count,
                'positive_count' => (int) $d->positive_count,
                'neutral_count' => (int) $d->neutral_count,
                'negative_count' => (int) $d->negative_count,
            ]);

        return Inertia::render('Entities/Show', [
            'trend' => $trend,
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'type' => $entity->type->value,
                'type_label' => $entity->type->label(),
                'description' => $entity->description,
                'searchable' => $entity->searchable,
                'rankable' => $entity->rankable,
                'category' => [
                    'id' => $entity->category->id,
                    'name' => $entity->category->name,
                    'slug' => $entity->category->slug,
                ],
                'parent' => $entity->parent ? [
                    'id' => $entity->parent->id,
                    'name' => $entity->parent->name,
                    'slug' => $entity->parent->slug,
                ] : null,
                'aliases' => $entity->aliases->map(fn ($alias) => [
                    'id' => $alias->id,
                    'alias' => $alias->alias,
                    'alias_type' => $alias->alias_type->value,
                ]),
            ],
            'period' => $selectedPeriod->value,
            'availablePeriods' => array_map(fn (Period $p) => $p->value, Period::cases()),
            'sentiment' => $sentimentData,
            'rating' => [
                'rating_count' => $entity->ratingSnapshot
                    ? (int) $entity->ratingSnapshot->rating_count
                    : 0,
                'rating_average' => $entity->ratingSnapshot?->rating_average === null
                    ? null
                    : (float) $entity->ratingSnapshot->rating_average,
                'user_rating' => $userRating === null ? null : (int) $userRating,
            ],
            'themes' => $themesData,
            'relatedEntities' => $relatedEntities->map(fn (Entity $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'slug' => $e->slug,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
            ]),
            'specs' => $this->buildSpecs($entity),
        ]);
    }

    /**
     * Manually curated reference specs (docs/03, ADR-008 clarification) — static
     * admin-entered data, never derived from sentiment. Renders as a plain
     * label/value list; null values are skipped rather than shown blank.
     *
     * @return array{title: string, items: list<array{label: string, value: string}>}|null
     */
    private function buildSpecs(Entity $entity): ?array
    {
        [$title, $spec, $labels] = match (true) {
            $entity->smartphoneSpec !== null => ['Spesifikasi Smartphone', $entity->smartphoneSpec, [
                'chipset' => 'Chipset',
                'ram' => 'RAM',
                'storage' => 'Storage',
                'screen_size_inch' => 'Layar',
                'screen_type' => 'Tipe Layar',
                'rear_camera' => 'Kamera Belakang',
                'front_camera' => 'Kamera Depan',
                'battery_mah' => 'Baterai',
                'fast_charging_watt' => 'Fast Charging',
                'os' => 'OS',
                'network' => 'Jaringan',
                'release_year' => 'Tahun Rilis',
            ]],
            $entity->carSpec !== null => ['Spesifikasi Mobil', $entity->carSpec, [
                'body_type' => 'Tipe Bodi',
                'engine_cc' => 'Kapasitas Mesin',
                'cylinder_count' => 'Jumlah Silinder',
                'fuel_type' => 'Bahan Bakar',
                'power_hp' => 'Tenaga',
                'torque_nm' => 'Torsi',
                'transmission' => 'Transmisi',
                'drivetrain' => 'Penggerak',
                'fuel_tank_liter' => 'Tangki BBM',
                'seating_capacity' => 'Kapasitas Duduk',
                'dimensions_mm' => 'Dimensi',
                'release_year' => 'Tahun Rilis',
            ]],
            $entity->motorcycleSpec !== null => ['Spesifikasi Motor', $entity->motorcycleSpec, [
                'body_type' => 'Tipe',
                'engine_cc' => 'Kapasitas Mesin',
                'cooling_system' => 'Pendingin',
                'fuel_type' => 'Bahan Bakar',
                'power_hp' => 'Tenaga',
                'torque_nm' => 'Torsi',
                'transmission' => 'Transmisi',
                'fuel_tank_liter' => 'Tangki BBM',
                'weight_kg' => 'Berat',
                'braking_system' => 'Pengereman',
                'release_year' => 'Tahun Rilis',
            ]],
            $entity->personProfile !== null => ['Profil', $entity->personProfile, [
                'birth_date' => 'Tanggal Lahir',
                'birth_place' => 'Tempat Lahir',
                'occupation' => 'Profesi / Jabatan',
                'affiliation' => 'Afiliasi',
                'active_since_year' => 'Aktif Sejak',
                'official_website' => 'Website Resmi',
            ]],
            default => [null, null, null],
        };

        if ($spec === null) {
            return null;
        }

        $units = [
            'screen_size_inch' => ' inci',
            'engine_cc' => ' cc',
            'power_hp' => ' hp',
            'torque_nm' => ' Nm',
            'battery_mah' => ' mAh',
            'fast_charging_watt' => ' Watt',
            'fuel_tank_liter' => ' liter',
            'weight_kg' => ' kg',
        ];

        $items = [];
        foreach ($labels as $key => $label) {
            $value = $spec->getAttribute($key);
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'birth_date') {
                $value = $value->format('d M Y');
            }

            $items[] = [
                'label' => $label,
                'value' => $value.($units[$key] ?? ''),
            ];
        }

        if ($items === []) {
            return null;
        }

        return ['title' => $title, 'items' => $items];
    }
}
