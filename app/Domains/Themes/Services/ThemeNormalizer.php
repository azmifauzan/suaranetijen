<?php

namespace App\Domains\Themes\Services;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;
use Illuminate\Support\Str;

class ThemeNormalizer
{
    /**
     * Normalize phrase using shared TextNormalizer.
     */
    public function normalize(string $phrase): string
    {
        return TextNormalizer::normalize($phrase);
    }

    /**
     * Resolve a Theme model by phrase (checking canonical key, slug, or alias).
     */
    public function resolveTheme(string $phrase): ?Theme
    {
        $normalized = $this->normalize($phrase);
        if ($normalized === '') {
            return null;
        }

        $slug = Str::slug($normalized);

        // 1. Exact match on slug or canonical key
        $theme = Theme::query()
            ->where('slug', $slug)
            ->orWhere('canonical_key', $normalized)
            ->first();

        if ($theme !== null) {
            return $theme;
        }

        // 2. Match on alias
        $alias = ThemeAlias::query()
            ->with('theme')
            ->where('normalized_alias', $normalized)
            ->first();

        return $alias?->theme;
    }

    /**
     * Seed baseline canonical themes and aliases per docs/25.
     *
     * @return array<string, Theme>
     */
    public function seedDefaultThemes(): array
    {
        $defaults = [
            [
                'canonical_key' => 'speed_fast',
                'slug' => 'cepat',
                'display_label' => 'Cepat',
                'aliases' => ['cepat', 'ngebut', 'kencang', 'responsif', 'wus wus', 'anti lelet', 'speed kencang'],
            ],
            [
                'canonical_key' => 'speed_slow',
                'slug' => 'lambat',
                'display_label' => 'Lambat',
                'aliases' => ['lambat', 'lemot', 'lelet', 'lelet banget', 'lola', 'buffering', 'lemot parah'],
            ],
            [
                'canonical_key' => 'price_affordable',
                'slug' => 'murah',
                'display_label' => 'Murah',
                'aliases' => ['murah', 'terjangkau', 'harga oke', 'ramah kantong', 'worth it', 'bersahabat', 'ekonomis', 'hemat'],
            ],
            [
                'canonical_key' => 'price_expensive',
                'slug' => 'mahal',
                'display_label' => 'Mahal',
                'aliases' => ['mahal', 'overprice', 'kemahalan', 'boros', 'mahal banget', 'pricey'],
            ],
            [
                'canonical_key' => 'reliability_reliable',
                'slug' => 'handal',
                'display_label' => 'Handal',
                'aliases' => ['handal', 'andal', 'stabil', 'awet', 'jarang error', 'uptime bagus', 'reliable', 'koneksi stabil'],
            ],
            [
                'canonical_key' => 'reliability_unreliable',
                'slug' => 'sering-error',
                'display_label' => 'Sering Error',
                'aliases' => ['sering error', 'sering gangguan', 'down', 'sering down', 'downtime', 'ngedrop', 'sering ngedrop', 'suka error'],
            ],
            [
                'canonical_key' => 'support_good',
                'slug' => 'support-bagus',
                'display_label' => 'Support Bagus',
                'aliases' => ['support bagus', 'cs ramah', 'fast respon', 'cs cepat', 'bantuan responsif', 'pelayanan ramah', 'support mantap', 'cs solutif'],
            ],
            [
                'canonical_key' => 'support_slow',
                'slug' => 'support-lambat',
                'display_label' => 'Support Lambat',
                'aliases' => ['support lambat', 'cs lambat', 'slow respon', 'cs judes', 'cs lelet', 'ticket lama', 'support buruk', 'cs bot mulu'],
            ],
            [
                'canonical_key' => 'quality_good',
                'slug' => 'kualitas-bagus',
                'display_label' => 'Kualitas Bagus',
                'aliases' => ['kualitas bagus', 'mantap', 'oke banget', 'memuaskan', 'juara', 'top', 'rekomen', 'recommended', 'bagus banget'],
            ],
            [
                'canonical_key' => 'quality_poor',
                'slug' => 'kualitas-buruk',
                'display_label' => 'Kualitas Buruk',
                'aliases' => ['kualitas buruk', 'mengecewakan', 'jelek', 'jelek banget', 'zonk', 'ampas', 'kapok', 'buruk'],
            ],
        ];

        $created = [];

        foreach ($defaults as $data) {
            /** @var Theme $theme */
            $theme = Theme::query()->updateOrCreate(
                ['canonical_key' => $data['canonical_key']],
                [
                    'slug' => $data['slug'],
                    'display_label' => $data['display_label'],
                ]
            );

            foreach ($data['aliases'] as $alias) {
                $normalized = $this->normalize($alias);
                ThemeAlias::query()->updateOrCreate(
                    [
                        'theme_id' => $theme->id,
                        'normalized_alias' => $normalized,
                    ],
                    [
                        'alias' => $alias,
                    ]
                );
            }

            $created[$data['canonical_key']] = $theme;
        }

        return $created;
    }
}
