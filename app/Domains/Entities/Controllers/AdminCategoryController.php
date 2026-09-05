<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Requests\StoreCategoryRequest;
use App\Domains\Entities\Requests\UpdateCategoryRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminCategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $categories = Category::query()
            ->with(['parent'])
            ->withCount('entities')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $parentCategories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'parent_categories' => $parentCategories,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if (empty($validated['status'])) {
            $validated['status'] = CategoryStatus::Active;
        }

        Category::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category created successfully.']);

        return redirect()->back();
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category updated successfully.']);

        return redirect()->back();
    }

    /**
     * Toggle the active/disabled status of a category (disable without delete).
     */
    public function toggleStatus(Category $category): RedirectResponse
    {
        $newStatus = $category->status === CategoryStatus::Active
            ? CategoryStatus::Disabled
            : CategoryStatus::Active;

        $category->update(['status' => $newStatus]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category status changed to {$newStatus->value}."]);

        return redirect()->back();
    }
}
