<?php

namespace Database\Factories;

use App\Domains\Blog\Enums\BlogPostStatus;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'excerpt' => fake()->paragraph(),
            'body' => fake()->paragraphs(3, true),
            'cover_image_path' => null,
            'status' => BlogPostStatus::Draft,
            'published_at' => null,
            'author_id' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => BlogPostStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function published(?\DateTimeInterface $publishedAt = null): static
    {
        return $this->state(fn () => [
            'status' => BlogPostStatus::Published,
            'published_at' => $publishedAt ?? now()->subDay(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn () => [
            'status' => BlogPostStatus::Published,
            'published_at' => now()->addDays(2),
        ]);
    }

    public function withCover(string $path = 'blog/covers/sample.webp'): static
    {
        return $this->state(fn () => [
            'cover_image_path' => $path,
        ]);
    }
}
