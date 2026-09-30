<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Models\EntitySearchDocument;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\EntityThemeSummary;
use Illuminate\Database\Eloquent\Model;

class EntitySearchDocumentBuilder
{
    /**
     * Negation polarity markers. Themes containing any of these words are excluded
     * from theme_text so that negated mentions (e.g. "tidak murah") do not boost
     * the entity for positive keywords (e.g. "murah").
     *
     * @var list<string>
     */
    protected const NEGATION_MARKERS = [
        'tidak',
        'gak',
        'nggak',
        'kurang',
        'bukan',
    ];

    /**
     * Build and persist the search document for a given entity.
     */
    public function buildForEntity(Entity $entity): EntitySearchDocument
    {
        $descriptionText = $this->buildDescriptionText($entity);
        $themeText = $this->buildThemeText($entity);
        $specText = $this->buildSpecText($entity);
        $summaryText = $this->buildSummaryText($entity);

        return EntitySearchDocument::updateOrCreate(
            ['entity_id' => $entity->id],
            [
                'description_text' => $descriptionText,
                'theme_text' => $themeText,
                'spec_text' => $specText,
                'summary_text' => $summaryText,
            ]
        );
    }

    /**
     * Build normalized description text.
     */
    protected function buildDescriptionText(Entity $entity): ?string
    {
        if (empty($entity->description)) {
            return null;
        }

        $normalized = TextNormalizer::normalize($entity->description);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Build normalized theme text from 365d snapshot (fallback all-time),
     * filtering out themes with negation markers.
     */
    protected function buildThemeText(Entity $entity): ?string
    {
        // 1. Try 365d snapshots first
        $snapshots = EntityThemeSnapshot::query()
            ->with('theme')
            ->where('entity_id', $entity->id)
            ->where('window', Period::OneYear)
            ->where('observation_count', '>', 0)
            ->orderByDesc('observation_count')
            ->get();

        // 2. Fallback to all-time snapshots
        if ($snapshots->isEmpty()) {
            $snapshots = EntityThemeSnapshot::query()
                ->with('theme')
                ->where('entity_id', $entity->id)
                ->where('window', Period::All)
                ->where('observation_count', '>', 0)
                ->orderByDesc('observation_count')
                ->get();
        }

        if ($snapshots->isEmpty()) {
            return null;
        }

        $validLabels = [];

        foreach ($snapshots as $snapshot) {
            $theme = $snapshot->theme;
            if ($theme->display_label === '') {
                continue;
            }

            if (self::hasNegationMarker($theme->display_label)) {
                continue;
            }

            $normalizedLabel = TextNormalizer::normalize($theme->display_label);
            if ($normalizedLabel !== '') {
                $validLabels[] = $normalizedLabel;
            }
        }

        if ($validLabels === []) {
            return null;
        }

        return implode(' ', array_unique($validLabels));
    }

    /**
     * Check if a theme label contains negation polarity markers.
     */
    public static function hasNegationMarker(string $label): bool
    {
        $normalized = TextNormalizer::normalize($label);
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_intersect($words, self::NEGATION_MARKERS) !== [];
    }

    /**
     * Build normalized spec text from any associated specification model.
     */
    protected function buildSpecText(Entity $entity): ?string
    {
        $entity->loadMissing(['smartphoneSpec', 'carSpec', 'motorcycleSpec', 'personProfile']);

        $specModel = $entity->smartphoneSpec
            ?? $entity->carSpec
            ?? $entity->motorcycleSpec
            ?? $entity->personProfile;

        if (! $specModel) {
            return null;
        }

        $values = [];
        $ignoredFields = ['id', 'entity_id', 'created_at', 'updated_at'];

        foreach ($specModel->getAttributes() as $key => $val) {
            if (in_array($key, $ignoredFields, true) || $val === null || $val === '') {
                continue;
            }

            $strVal = (string) $val;
            $normalized = TextNormalizer::normalize($strVal);
            if ($normalized !== '') {
                $values[] = $normalized;
            }
        }

        if ($values === []) {
            return null;
        }

        return implode(' ', array_unique($values));
    }

    /**
     * Build normalized summary text from EntityThemeSummary.
     */
    protected function buildSummaryText(Entity $entity): ?string
    {
        $summaryModel = EntityThemeSummary::where('entity_id', $entity->id)->first();

        if (! $summaryModel) {
            return null;
        }

        $parts = [];

        if (! empty($summaryModel->summary)) {
            $normalizedSummary = TextNormalizer::normalize($summaryModel->summary);
            if ($normalizedSummary !== '') {
                $parts[] = $normalizedSummary;
            }
        }

        foreach ($summaryModel->theme_notes as $note) {
            $normalizedNote = TextNormalizer::normalize($note);
            if ($normalizedNote !== '') {
                $parts[] = $normalizedNote;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(' ', array_unique($parts));
    }
}
