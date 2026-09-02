<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SeedEntityImporter
{
    /**
     * Map child category names to their parent category names per docs/03 taxonomy.
     *
     * @var array<string, string>
     */
    protected array $categoryTaxonomy = [
        'Smartphone' => 'Technology',
        'Cloud & Hosting' => 'Technology',
        'ISP & Telco' => 'Technology',
        'Mobil' => 'Automotive',
        'Motor' => 'Automotive',
        'E-commerce' => 'Digital Services',
        'Ride Hailing' => 'Digital Services',
        'Logistics' => 'Digital Services',
        'Brand Umum' => 'Consumer Brands',
    ];

    /**
     * Import entities from a CSV file path.
     *
     * @return array{categories: int, entities: int, aliases: int}
     */
    public function import(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Seed file not found at: {$filePath}");
        }

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Cannot open seed file at: {$filePath}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('Seed CSV file is empty');
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 6) {
                continue;
            }

            $rows[] = [
                'name' => trim($data[0]),
                'type' => trim($data[1]),
                'category' => trim($data[2]),
                'parent' => ! empty(trim($data[3])) ? trim($data[3]) : null,
                'aliases' => trim($data[4]),
                'description' => ! empty(trim($data[5])) ? trim($data[5]) : null,
            ];
        }

        fclose($handle);

        return DB::transaction(function () use ($rows): array {
            $this->ensureCategoriesExist();

            $categoryMap = Category::pluck('id', 'name')->all();

            // Pass 1: Upsert all entities
            /** @var array<string, Entity> $entityMapByName */
            $entityMapByName = [];

            foreach ($rows as $row) {
                $categoryName = $row['category'];
                $categoryId = $categoryMap[$categoryName] ?? null;

                if (! $categoryId) {
                    $category = Category::firstOrCreate(
                        ['slug' => Str::slug($categoryName)],
                        ['name' => $categoryName, 'status' => CategoryStatus::Active]
                    );
                    $categoryId = $category->id;
                    $categoryMap[$categoryName] = $categoryId;
                }

                $type = EntityType::from($row['type']);
                $slug = Str::slug($row['name']);

                $entity = Entity::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'category_id' => $categoryId,
                        'name' => $row['name'],
                        'type' => $type,
                        'description' => $row['description'],
                        'status' => EntityStatus::Active,
                        'searchable' => true,
                        'rankable' => true,
                    ]
                );

                $entityMapByName[$entity->name] = $entity;
                $entityMapByName[$entity->slug] = $entity;
            }

            // Pass 2: Connect parent-child relationships
            foreach ($rows as $row) {
                if (! empty($row['parent'])) {
                    $parentNameOrSlug = $row['parent'];
                    $parent = $entityMapByName[$parentNameOrSlug] ?? null;

                    if ($parent) {
                        $entitySlug = Str::slug($row['name']);
                        $entity = $entityMapByName[$entitySlug] ?? null;

                        if ($entity && $entity->parent_id !== $parent->id) {
                            $entity->parent_id = $parent->id;
                            $entity->save();
                        }
                    }
                }
            }

            // Pass 3: Upsert aliases
            foreach ($rows as $row) {
                $entitySlug = Str::slug($row['name']);
                $entity = $entityMapByName[$entitySlug] ?? null;

                if (! $entity) {
                    continue;
                }

                // 1. Primary alias from entity name
                $primaryNormalized = TextNormalizer::normalize($entity->name);
                EntityAlias::updateOrCreate(
                    [
                        'entity_id' => $entity->id,
                        'normalized_alias' => $primaryNormalized,
                    ],
                    [
                        'alias' => $entity->name,
                        'alias_type' => AliasType::Primary,
                    ]
                );

                // 2. Extra aliases from CSV
                if (! empty($row['aliases'])) {
                    $aliasList = explode('|', $row['aliases']);

                    foreach ($aliasList as $rawAlias) {
                        $rawAlias = trim($rawAlias);

                        if ($rawAlias === '') {
                            continue;
                        }

                        $normalized = TextNormalizer::normalize($rawAlias);

                        if ($normalized === '') {
                            continue;
                        }

                        EntityAlias::updateOrCreate(
                            [
                                'entity_id' => $entity->id,
                                'normalized_alias' => $normalized,
                            ],
                            [
                                'alias' => $rawAlias,
                                'alias_type' => AliasType::CommonVariant,
                            ]
                        );
                    }
                }
            }

            return [
                'categories' => Category::count(),
                'entities' => Entity::count(),
                'aliases' => EntityAlias::count(),
            ];
        });
    }

    /**
     * Ensure top-level parent categories and known child categories exist.
     */
    protected function ensureCategoriesExist(): void
    {
        $parentCategories = [
            'Technology' => 'technology',
            'Automotive' => 'automotive',
            'Digital Services' => 'digital-services',
            'Consumer Brands' => 'consumer-brands',
        ];

        $parentIdMap = [];

        foreach ($parentCategories as $name => $slug) {
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'parent_id' => null,
                    'status' => CategoryStatus::Active,
                ]
            );

            $parentIdMap[$name] = $category->id;
        }

        foreach ($this->categoryTaxonomy as $childName => $parentName) {
            $parentId = $parentIdMap[$parentName] ?? null;
            $slug = Str::slug($childName);

            Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $childName,
                    'parent_id' => $parentId,
                    'status' => CategoryStatus::Active,
                ]
            );
        }
    }
}
