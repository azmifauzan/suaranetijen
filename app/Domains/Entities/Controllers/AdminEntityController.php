<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Requests\StoreEntityRequest;
use App\Domains\Entities\Requests\UpdateEntityRequest;
use App\Domains\Entities\Services\TextNormalizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminEntityController extends Controller
{
    /**
     * Display a listing of entities.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $categoryId = $request->integer('category_id');
        $type = $request->string('type')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $entities = Entity::query()
            ->with(['category', 'parent'])
            ->withCount('aliases')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%");
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $parentBrands = Entity::query()->where('type', EntityType::Brand)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Entities/Index', [
            'entities' => $entities,
            'categories' => $categories,
            'parent_brands' => $parentBrands,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'type' => $type !== '' ? $type : null,
                'status' => $status !== '' ? $status : null,
            ],
        ]);
    }

    /**
     * Store a newly created entity.
     */
    public function store(StoreEntityRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if (empty($validated['status'])) {
            $validated['status'] = EntityStatus::Active;
        }

        DB::transaction(function () use ($validated): void {
            $entity = Entity::create($validated);

            // Automatically create primary alias
            EntityAlias::create([
                'entity_id' => $entity->id,
                'alias' => $entity->name,
                'normalized_alias' => TextNormalizer::normalize($entity->name),
                'alias_type' => AliasType::Primary,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entity created successfully.']);

        return redirect()->back();
    }

    /**
     * Show the edit page for a specific entity, including its aliases.
     */
    public function edit(Entity $entity): Response
    {
        $entity->load(['category', 'parent', 'aliases']);

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $parentBrands = Entity::query()
            ->where('type', EntityType::Brand)
            ->where('id', '!=', $entity->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Entities/Form', [
            'entity' => $entity,
            'categories' => $categories,
            'parent_brands' => $parentBrands,
        ]);
    }

    /**
     * Update the specified entity.
     */
    public function update(UpdateEntityRequest $request, Entity $entity): RedirectResponse
    {
        $entity->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entity updated successfully.']);

        return redirect()->back();
    }

    /**
     * Toggle active/disabled status (disable without delete per docs/02).
     */
    public function toggleStatus(Entity $entity): RedirectResponse
    {
        $newStatus = $entity->status === EntityStatus::Active
            ? EntityStatus::Disabled
            : EntityStatus::Active;

        $entity->update(['status' => $newStatus]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Entity status changed to {$newStatus->value}."]);

        return redirect()->back();
    }
}
