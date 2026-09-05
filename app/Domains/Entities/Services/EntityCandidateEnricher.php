<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;

class EntityCandidateEnricher
{
    public function __construct(private readonly LlmClient $client) {}

    /**
     * @param  list<string>  $rawTerms
     * @return array{suggested_name: string|null, suggested_entity_type: string|null, suggested_category_id: int|null, suggested_aliases: list<string>, reasoning: string|null}
     */
    public function enrich(string $normalizedTerm, array $rawTerms): array
    {
        $categoryNames = Category::query()->pluck('name');

        $suggestion = $this->client->chat(
            [
                [
                    'role' => 'system',
                    'content' => 'You suggest metadata for a new brand/product/service entity candidate for an '
                        .'Indonesian public-sentiment index. Only ever pick suggested_category from the exact '
                        .'list of category names given to you. Never invent facts you are not confident about.',
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
                        'suggested_name' => ['type' => 'string'],
                        'suggested_entity_type' => ['type' => 'string', 'enum' => array_column(EntityType::cases(), 'value')],
                        'suggested_category' => ['type' => 'string'],
                        'suggested_aliases' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'reasoning' => ['type' => 'string'],
                    ],
                    'required' => ['suggested_name', 'suggested_entity_type', 'suggested_category', 'suggested_aliases', 'reasoning'],
                    'additionalProperties' => false,
                ],
            ]
        );

        $categoryName = (string) ($suggestion['suggested_category'] ?? '');
        $entityType = (string) ($suggestion['suggested_entity_type'] ?? '');

        return [
            'suggested_name' => $suggestion['suggested_name'] ?? null,
            'suggested_entity_type' => EntityType::tryFrom($entityType)?->value,
            'suggested_category_id' => Category::query()->whereRaw('lower(name) = ?', [mb_strtolower($categoryName)])->value('id'),
            'suggested_aliases' => array_values(array_filter((array) ($suggestion['suggested_aliases'] ?? []), 'is_string')),
            'reasoning' => $suggestion['reasoning'] ?? null,
        ];
    }
}
