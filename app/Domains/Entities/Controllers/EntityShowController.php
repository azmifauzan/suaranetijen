<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\Entity;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class EntityShowController extends Controller
{
    /**
     * Display the specified entity public page.
     */
    public function show(string $slug): Response
    {
        /** @var Entity $entity */
        $entity = Entity::query()
            ->with(['category', 'parent', 'aliases'])
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        return Inertia::render('Entities/Show', [
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
        ]);
    }
}
