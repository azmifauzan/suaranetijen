<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Requests\StoreEntityAliasRequest;
use App\Domains\Entities\Services\TextNormalizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AdminEntityAliasController extends Controller
{
    /**
     * Store a newly created alias for an entity.
     */
    public function store(StoreEntityAliasRequest $request, Entity $entity): RedirectResponse
    {
        $validated = $request->validated();

        $aliasText = trim($validated['alias']);
        $normalized = TextNormalizer::normalize($aliasText);
        $aliasType = isset($validated['alias_type'])
            ? AliasType::from($validated['alias_type'])
            : AliasType::CommonVariant;

        EntityAlias::create([
            'entity_id' => $entity->id,
            'alias' => $aliasText,
            'normalized_alias' => $normalized,
            'alias_type' => $aliasType,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alias added successfully.']);

        return redirect()->back();
    }

    /**
     * Remove an alias from an entity.
     */
    public function destroy(Entity $entity, EntityAlias $alias): RedirectResponse
    {
        if ($alias->entity_id !== $entity->id) {
            abort(404);
        }

        $alias->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alias removed successfully.']);

        return redirect()->back();
    }
}
