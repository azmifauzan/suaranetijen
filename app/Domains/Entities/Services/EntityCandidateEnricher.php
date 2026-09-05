<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;

class EntityCandidateEnricher
{
    public function __construct(private readonly LlmClient $client) {}

    /**
     * @param  list<string>  $rawTerms
     * @return array{is_relevant: bool, suggested_name: string|null, suggested_entity_type: string|null, suggested_category_id: int|null, suggested_aliases: list<string>, reasoning: string|null}
     */
    public function enrich(string $normalizedTerm, array $rawTerms): array
    {
        $categoryNames = Category::query()->pluck('name');

        $suggestion = $this->client->chat(
            [
                [
                    'role' => 'system',
                    'content' => 'You suggest metadata for a new brand/product/service/person entity candidate '
                        .'for an Indonesian public-sentiment index. Only ever pick suggested_category from the '
                        .'exact list of category names given to you. Never invent facts you are not confident '
                        .'about. Many candidate terms come from raw search-trend data and are NOT a genuine '
                        .'brand/product/service/public-figure at all — sports match results and fixtures, '
                        .'generic news headlines, transit/event schedules, holidays, and political topics or '
                        .'policy issues (out of scope for this index) are all common noise. A specific named '
                        .'public figure (e.g. a business leader, official, or celebrity) is a legitimate '
                        .'"person" candidate, not noise, even when the term itself is politics-adjacent (e.g. '
                        .'a minister\'s name) — only the underlying policy/political topic is out of scope, not '
                        .'the person. Set is_relevant to false only for genuine noise, never just to avoid a '
                        .'person or an unfamiliar name — do not invent a brand interpretation just to fill the '
                        .'other fields either.',
                ],
                [
                    'role' => 'user',
                    'content' => "Candidate term: \"{$normalizedTerm}\"\n"
                        .'Raw variants observed: '.implode(', ', $rawTerms)."\n"
                        .'Existing categories: '.$categoryNames->implode(', '),
                ],
            ],
            [
                'name' => 'entity_candidate_suggestion',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'is_relevant' => ['type' => 'boolean'],
                        'suggested_name' => ['type' => 'string'],
                        'suggested_entity_type' => ['type' => 'string', 'enum' => array_column(EntityType::cases(), 'value')],
                        'suggested_category' => ['type' => 'string'],
                        'suggested_aliases' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'reasoning' => ['type' => 'string'],
                    ],
                    'required' => ['is_relevant', 'suggested_name', 'suggested_entity_type', 'suggested_category', 'suggested_aliases', 'reasoning'],
                    'additionalProperties' => false,
                ],
            ]
        );

        $isRelevant = (bool) ($suggestion['is_relevant'] ?? true);
        $categoryName = (string) ($suggestion['suggested_category'] ?? '');
        $entityType = (string) ($suggestion['suggested_entity_type'] ?? '');

        return [
            'is_relevant' => $isRelevant,
            'suggested_name' => $isRelevant ? ($suggestion['suggested_name'] ?? null) : null,
            'suggested_entity_type' => $isRelevant ? EntityType::tryFrom($entityType)?->value : null,
            'suggested_category_id' => $isRelevant
                ? Category::query()->whereRaw('lower(name) = ?', [mb_strtolower($categoryName)])->value('id')
                : null,
            'suggested_aliases' => $isRelevant
                ? array_values(array_filter((array) ($suggestion['suggested_aliases'] ?? []), 'is_string'))
                : [],
            'reasoning' => $suggestion['reasoning'] ?? null,
        ];
    }
}
