<?php

namespace App\Domains\Search\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Enums\SearchLandingPageSource;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\CandidateScannerService;
use App\Domains\Search\Services\TopicDraftWriter;
use App\Domains\Search\Services\TopicEntityList;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Services\PublicCopyGuard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminTopicsController extends Controller
{
    public function __construct(
        protected CandidateScannerService $scannerService,
        protected TopicDraftWriter $draftWriter,
        protected TopicEntityList $entityList
    ) {}

    /**
     * List topics filtered by status tab.
     */
    public function index(Request $request): Response
    {
        $statusValue = (string) $request->query('status', 'candidate');
        $status = SearchLandingPageStatus::tryFrom($statusValue) ?? SearchLandingPageStatus::Candidate;
        $search = $request->query('q');

        $counts = [
            'candidate' => SearchLandingPage::candidate()->count(),
            'draft' => SearchLandingPage::draft()->count(),
            'published' => SearchLandingPage::published()->count(),
            'rejected' => SearchLandingPage::rejected()->count(),
        ];

        $query = SearchLandingPage::query()
            ->where('status', $status)
            ->with(['category:id,name', 'themes:id,display_label']);

        if (is_string($search) && trim($search) !== '') {
            $trimmed = trim($search);
            $query->where(function ($q) use ($trimmed) {
                $q->where('keyword', 'like', "%{$trimmed}%")
                    ->orWhere('title', 'like', "%{$trimmed}%")
                    ->orWhere('slug', 'like', "%{$trimmed}%");
            });
        }

        if ($status === SearchLandingPageStatus::Candidate) {
            $query->orderByDesc('candidate_signal');
        } elseif ($status === SearchLandingPageStatus::Published) {
            $query->orderByDesc('published_at');
        } else {
            $query->latest('updated_at');
        }

        $topics = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Topics/Index', [
            'topics' => $topics,
            'counts' => $counts,
            'currentStatus' => $status->value,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    /**
     * Show create manual topic form.
     */
    public function create(): Response
    {
        $categories = Category::query()
            ->active()
            ->whereDoesntHave('children')
            ->excludePublicFigure()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Topics/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store manual topic.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:155'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'theme_ids' => ['nullable', 'array'],
            'theme_ids.*' => ['exists:themes,id'],
        ]);

        $this->assertCopyAllowed($validated);

        $norm = TextNormalizer::normalize($validated['keyword']);

        if (SearchLandingPage::where('normalized_keyword', $norm)->exists()) {
            throw ValidationException::withMessages([
                'keyword' => 'Topik dengan keyword ini sudah terdaftar.',
            ]);
        }

        if (! empty($validated['category_id'])) {
            $category = Category::find((int) $validated['category_id']);
            if ($category && $category->isPublicFigureCategory()) {
                throw ValidationException::withMessages([
                    'category_id' => 'Kategori Tokoh Publik tidak diperbolehkan untuk topik landing page.',
                ]);
            }
        }

        $slug = $this->scannerService->generateUniqueSlug($validated['keyword']);

        $topic = SearchLandingPage::create([
            'slug' => $slug,
            'keyword' => $validated['keyword'],
            'normalized_keyword' => $norm,
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'source' => SearchLandingPageSource::Manual,
            'status' => SearchLandingPageStatus::Draft,
            'candidate_signal' => 1,
        ]);

        if (! empty($validated['theme_ids'])) {
            $topic->themes()->sync($validated['theme_ids']);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Topik berhasil dibuat.',
        ]);

        return redirect()->route('admin.topics.edit', $topic);
    }

    /**
     * Edit topic page.
     */
    public function edit(SearchLandingPage $topic): Response
    {
        $topic->load(['category', 'themes']);

        $preview = $this->entityList->get($topic, useCache: false);

        $categories = Category::query()
            ->active()
            ->whereDoesntHave('children')
            ->excludePublicFigure()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Topics/Edit', [
            'topic' => [
                'id' => $topic->id,
                'slug' => $topic->slug,
                'keyword' => $topic->keyword,
                'title' => $topic->title,
                'meta_description' => $topic->meta_description,
                'intro' => $topic->intro,
                'category_id' => $topic->category_id,
                'status' => $topic->status->value,
                'source' => $topic->source->value,
                'candidate_signal' => $topic->candidate_signal,
                'published_at' => $topic->published_at?->format('d M Y H:i'),
                'llm_drafted_at' => $topic->llm_drafted_at?->format('d M Y H:i'),
                'themes' => $topic->themes->map(fn (Theme $t) => [
                    'id' => $t->id,
                    'display_label' => $t->display_label,
                ]),
            ],
            'categories' => $categories,
            'preview' => [
                'entity_count' => count($preview['entities']),
                'is_indexable' => $preview['is_indexable'],
                'qualifying_count' => $preview['qualifying_count'],
                'window' => $preview['window'],
            ],
            'isSlugLocked' => $topic->status === SearchLandingPageStatus::Published,
        ]);
    }

    /**
     * Update topic draft.
     */
    public function update(Request $request, SearchLandingPage $topic): RedirectResponse
    {
        $rules = [
            'keyword' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:155'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'theme_ids' => ['nullable', 'array'],
            'theme_ids.*' => ['exists:themes,id'],
        ];

        // Slug is strictly locked once published
        if ($topic->status !== SearchLandingPageStatus::Published) {
            $rules['slug'] = [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('search_landing_pages', 'slug')->ignore($topic->id),
            ];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['category_id'])) {
            $category = Category::find((int) $validated['category_id']);
            if ($category && $category->isPublicFigureCategory()) {
                throw ValidationException::withMessages([
                    'category_id' => 'Kategori Tokoh Publik tidak diperbolehkan untuk topik landing page.',
                ]);
            }
        }

        $this->assertCopyAllowed($validated);

        $updateData = [
            'keyword' => $validated['keyword'],
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'intro' => $validated['intro'] ?? null,
        ];

        if ($topic->status !== SearchLandingPageStatus::Published && isset($validated['slug'])) {
            $updateData['slug'] = $validated['slug'];
        }

        $topic->update($updateData);

        if (array_key_exists('theme_ids', $validated)) {
            $topic->themes()->sync((array) $validated['theme_ids']);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Perubahan topik berhasil disimpan.',
        ]);

        return redirect()->back();
    }

    /**
     * Publish topic.
     */
    public function publish(SearchLandingPage $topic): RedirectResponse
    {
        if (in_array($topic->status, [SearchLandingPageStatus::Published, SearchLandingPageStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya topik berstatus kandidat atau draft yang bisa dipublikasikan.',
            ]);
        }

        $errors = [];

        // 1. Category validation
        if (empty($topic->category_id)) {
            $errors['category_id'] = 'Kategori wajib dipilih sebelum publish.';
        } else {
            $category = Category::find($topic->category_id);
            if (! $category || $category->isPublicFigureCategory()) {
                $errors['category_id'] = 'Kategori Tokoh Publik tidak diperbolehkan untuk topik landing page.';
            }
        }

        // 2. Themes validation
        if ($topic->themes()->count() < 1) {
            $errors['theme_ids'] = 'Minimal satu tema harus dipilih sebelum publish.';
        }

        // 3. Title validation & copy guard
        if (empty($topic->title)) {
            $errors['title'] = 'Judul (Title) wajib diisi sebelum publish.';
        } elseif (! PublicCopyGuard::isAllowed($topic->title, 60)) {
            $errors['title'] = 'Judul melanggar aturan penulisan: tidak boleh superlatif (terbaik/terburuk), persentase, mention (@), atau URL.';
        }

        // 4. Meta description validation & copy guard
        if (empty($topic->meta_description)) {
            $errors['meta_description'] = 'Meta description wajib diisi sebelum publish.';
        } elseif (! PublicCopyGuard::isAllowed($topic->meta_description, 155)) {
            $errors['meta_description'] = 'Meta description melanggar aturan penulisan: tidak boleh superlatif (terbaik/terburuk), persentase, mention (@), atau URL.';
        }

        // 5. Intro validation & copy guard
        if (empty($topic->intro)) {
            $errors['intro'] = 'Intro teks wajib diisi sebelum publish.';
        } elseif (! PublicCopyGuard::isAllowed($topic->intro, 1000)) {
            $errors['intro'] = 'Intro melanggar aturan penulisan: tidak boleh superlatif (terbaik/terburuk), persentase, mention (@), atau URL.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $topic->update([
            'status' => SearchLandingPageStatus::Published,
            'published_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Topik berhasil dipublikasikan.',
        ]);

        return redirect()->back();
    }

    /**
     * Unpublish topic (revert to draft).
     */
    public function unpublish(SearchLandingPage $topic): RedirectResponse
    {
        $topic->update([
            'status' => SearchLandingPageStatus::Draft,
            'published_at' => null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Topik dikembalikan ke draft. URL publik sekarang menghasilkan 404.',
        ]);

        return redirect()->back();
    }

    /**
     * Reject topic permanently.
     */
    public function reject(SearchLandingPage $topic): RedirectResponse
    {
        $topic->update([
            'status' => SearchLandingPageStatus::Rejected,
            'published_at' => null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Topik ditolak secara permanen.',
        ]);

        return redirect()->back();
    }

    /**
     * Regenerate LLM draft.
     */
    public function regenerate(SearchLandingPage $topic): RedirectResponse
    {
        if ($topic->status === SearchLandingPageStatus::Published) {
            throw ValidationException::withMessages([
                'status' => 'Topik yang sudah published tidak bisa digenerate ulang. Unpublish dulu.',
            ]);
        }

        $status = $this->draftWriter->draft($topic);

        if ($status === SearchLandingPageStatus::Draft) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Draft LLM berhasil diperbarui.',
            ]);
        } elseif ($status === SearchLandingPageStatus::Rejected) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'LLM menilai topik ini tidak relevan dan mengubah status menjadi ditolak.',
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Gagal membuat draft LLM. Silakan coba lagi.',
            ]);
        }

        return redirect()->back();
    }

    /**
     * Async search themes that have snapshots in the given category.
     */
    public function searchThemes(Request $request): JsonResponse
    {
        $categoryId = $request->query('category_id');
        $query = (string) $request->query('q', '');

        $themeQuery = Theme::query();

        if (! empty($categoryId)) {
            $themeQuery->whereHas('snapshots', function ($sq) use ($categoryId) {
                $sq->whereHas('entity', fn ($eq) => $eq->where('category_id', $categoryId));
            });
        }

        if (trim($query) !== '') {
            $trimmed = trim($query);
            $themeQuery->where(function ($q) use ($trimmed) {
                $q->where('display_label', 'like', "%{$trimmed}%")
                    ->orWhere('canonical_key', 'like', "%{$trimmed}%");
            });
        }

        $themes = $themeQuery->limit(30)->get(['id', 'display_label', 'slug']);

        return response()->json($themes);
    }

    /**
     * Same public-copy rules as publish, applied to every save so a live topic
     * cannot be edited into a superlative or a link after the fact.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertCopyAllowed(array $validated): void
    {
        $limits = ['title' => 60, 'meta_description' => 155, 'intro' => 1000];
        $errors = [];

        foreach ($limits as $field => $max) {
            $value = trim((string) ($validated[$field] ?? ''));

            if ($value !== '' && ! PublicCopyGuard::isAllowed($value, $max)) {
                $errors[$field] = 'Teks melanggar aturan penulisan: tidak boleh superlatif (terbaik/terburuk), persentase, mention (@), atau URL.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
