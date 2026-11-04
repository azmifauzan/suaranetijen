<?php

namespace App\Domains\Blog\Controllers;

use App\Domains\Blog\Enums\BlogPostStatus;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Themes\Services\PublicCopyGuard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminBlogController extends Controller
{
    /**
     * List blog posts filtered by status and search query.
     */
    public function index(Request $request): Response
    {
        $statusValue = (string) $request->query('status', 'all');
        $search = $request->query('q');

        $counts = [
            'all' => BlogPost::count(),
            'draft' => BlogPost::draft()->count(),
            'published' => BlogPost::published()->count(),
        ];

        $query = BlogPost::query()->with('author:id,name')->withCount('entities');

        if ($statusValue === 'draft') {
            $query->where('status', BlogPostStatus::Draft);
        } elseif ($statusValue === 'published') {
            $query->where('status', BlogPostStatus::Published);
        }

        if (is_string($search) && trim($search) !== '') {
            $trimmed = trim($search);
            $query->where(function ($q) use ($trimmed): void {
                $q->where('title', 'like', "%{$trimmed}%")
                    ->orWhere('slug', 'like', "%{$trimmed}%");
            });
        }

        $query->latest('updated_at');

        $posts = $query->paginate(15)->withQueryString()->through(fn (BlogPost $post) => [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'status' => $post->status->value,
            'status_label' => $post->status->label(),
            'published_at' => $post->published_at?->format('d M Y H:i'),
            'cover_url' => $post->cover_url,
            'author_name' => $post->author->name ?? 'Tim SuaraNetijen',
            'entities_count' => $post->entities_count,
            'updated_at' => $post->updated_at?->format('d M Y H:i'),
        ]);

        return Inertia::render('Admin/Blog/Index', [
            'posts' => $posts,
            'counts' => $counts,
            'currentStatus' => $statusValue,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    /**
     * Show create article form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Blog/Create');
    }

    /**
     * Store new blog post.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:blog_posts,slug'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', 'exists:entities,id'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=1200'],
        ]);

        $this->assertCopyAllowed($validated);

        $slug = ! empty($validated['slug'])
            ? $validated['slug']
            : $this->uniqueSlug($validated['title']);

        $post = BlogPost::create([
            'slug' => $slug,
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'],
            'cover_image_path' => $request->hasFile('cover') ? $this->storeCover($request->file('cover')) : null,
            'status' => BlogPostStatus::Draft,
            'published_at' => ! empty($validated['published_at']) ? $validated['published_at'] : null,
            'author_id' => $request->user()?->id,
        ]);

        if (! empty($validated['entity_ids'])) {
            $post->entities()->sync($validated['entity_ids']);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Artikel berhasil dibuat sebagai draft.',
        ]);

        return redirect()->route('admin.blog.edit', $post);
    }

    /**
     * Show edit article form.
     */
    public function edit(BlogPost $post): Response
    {
        $post->load(['author:id,name', 'entities:id,name,slug']);

        return Inertia::render('Admin/Blog/Edit', [
            'post' => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'cover_image_path' => $post->cover_image_path,
                'cover_url' => $post->cover_url,
                'status' => $post->status->value,
                'status_label' => $post->status->label(),
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'author_id' => $post->author_id,
                'author_name' => $post->author->name ?? 'Tim SuaraNetijen',
                'entities' => $post->entities->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'slug' => $e->slug,
                ]),
            ],
            'isSlugLocked' => $post->status === BlogPostStatus::Published,
        ]);
    }

    /**
     * Update blog post.
     */
    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $rules = [
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', 'exists:entities,id'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=1200'],
        ];

        // Slug is strictly locked once published
        if ($post->status !== BlogPostStatus::Published) {
            $rules['slug'] = [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($post->id),
            ];
        }

        $validated = $request->validate($rules);
        $this->assertCopyAllowed($validated);

        $updateData = [
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'],
        ];

        // A published post must keep a date or it vanishes from the public site (live() needs it).
        if (! empty($validated['published_at'])) {
            $updateData['published_at'] = $validated['published_at'];
        } elseif ($post->status !== BlogPostStatus::Published) {
            $updateData['published_at'] = null;
        }

        if ($post->status !== BlogPostStatus::Published && isset($validated['slug'])) {
            $updateData['slug'] = $validated['slug'];
        }

        $oldCoverPath = null;
        if ($request->hasFile('cover')) {
            $oldCoverPath = $post->cover_image_path;
            $updateData['cover_image_path'] = $this->storeCover($request->file('cover'));
        }

        // Sync before update() so the saved event flushes public caches after the pivot changed.
        $post->entities()->sync($validated['entity_ids'] ?? []);
        $post->update($updateData);

        if ($oldCoverPath) {
            Storage::disk('media')->delete($oldCoverPath);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Perubahan artikel berhasil disimpan.',
        ]);

        return redirect()->back();
    }

    /**
     * Remove post cover image.
     */
    public function deleteCover(BlogPost $post): RedirectResponse
    {
        if ($post->cover_image_path) {
            $path = $post->cover_image_path;
            $post->update(['cover_image_path' => null]);
            Storage::disk('media')->delete($path);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cover artikel berhasil dihapus.',
        ]);

        return redirect()->back();
    }

    /**
     * Publish blog post.
     */
    public function publish(BlogPost $post): RedirectResponse
    {
        $errors = [];

        if (empty($post->title)) {
            $errors['title'] = 'Judul wajib diisi sebelum publikasi.';
        } elseif (! PublicCopyGuard::isAllowed($post->title, 160)) {
            $errors['title'] = 'Judul melanggar aturan penulisan: tidak boleh superlatif (terbaik/terburuk), persentase, mention (@), atau URL.';
        }

        if (empty($post->body)) {
            $errors['body'] = 'Konten artikel wajib diisi sebelum publikasi.';
        }

        if (! empty($post->excerpt) && ! PublicCopyGuard::isAllowed($post->excerpt, 300)) {
            $errors['excerpt'] = 'Excerpt melanggar aturan penulisan: tidak boleh superlatif (terbaik/terburuk), persentase, mention (@), atau URL.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $post->update([
            'status' => BlogPostStatus::Published,
            'published_at' => $post->published_at ?? now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Artikel berhasil dipublikasikan.',
        ]);

        return redirect()->back();
    }

    /**
     * Unpublish blog post (revert to draft).
     */
    public function unpublish(BlogPost $post): RedirectResponse
    {
        $post->update([
            'status' => BlogPostStatus::Draft,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Artikel dikembalikan ke draft. URL publik sekarang menghasilkan 404.',
        ]);

        return redirect()->back();
    }

    /**
     * Delete blog post (draft only).
     */
    public function destroy(BlogPost $post): RedirectResponse
    {
        if ($post->status === BlogPostStatus::Published) {
            throw ValidationException::withMessages([
                'status' => 'Artikel yang sudah dipublikasikan tidak dapat langsung dihapus. Tarik (unpublish) artikel terlebih dahulu.',
            ]);
        }

        $post->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft artikel berhasil dihapus.',
        ]);

        return redirect()->route('admin.blog.index');
    }

    /**
     * Render markdown preview on the server so admin preview matches public render exactly.
     */
    public function previewMarkdown(Request $request): JsonResponse
    {
        return response()->json([
            'html' => BlogPost::renderMarkdown((string) $request->input('markdown', '')),
        ]);
    }

    /**
     * Enforce PublicCopyGuard on title and excerpt.
     * Note: Body is NOT guarded per docs/33.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertCopyAllowed(array $validated): void
    {
        $limits = ['title' => 160, 'excerpt' => 300];
        $errors = [];

        foreach ($limits as $field => $max) {
            $value = trim((string) ($validated[$field] ?? ''));

            if ($value !== '' && preg_match('/[<>]/', $value) === 1) {
                $errors[$field] = 'Teks tidak boleh mengandung karakter < atau >.';
            } elseif ($value !== '' && ! PublicCopyGuard::isAllowed($value, $max)) {
                $errors[$field] = 'Teks melanggar aturan penulisan: tidak boleh superlatif (terbaik/terburuk), persentase, mention (@), atau URL.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Upload a cover to the media disk, failing loudly so a post never points at a missing object.
     */
    private function storeCover(UploadedFile $file): string
    {
        $path = Storage::disk('media')->putFileAs('blog/covers', $file, Str::uuid().'.'.$file->extension(), 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['cover' => 'Gagal mengunggah cover. Coba lagi.']);
        }

        return $path;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $count = 2;

        while (BlogPost::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }
}
