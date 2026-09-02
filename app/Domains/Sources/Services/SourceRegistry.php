<?php

namespace App\Domains\Sources\Services;

use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Models\Source;
use InvalidArgumentException;

class SourceRegistry
{
    /**
     * @var array<string, class-string<SourceAdapter>>
     */
    protected array $adapters = [
        'fake' => FakeSourceAdapter::class,
        'FakeSourceAdapter' => FakeSourceAdapter::class,
        'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter' => FakeSourceAdapter::class,
    ];

    /**
     * Register or override an adapter class.
     *
     * @param  class-string<SourceAdapter>  $adapterClass
     */
    public function register(string $key, string $adapterClass): void
    {
        $this->adapters[$key] = $adapterClass;
    }

    /**
     * Resolve a SourceAdapter instance for a given Source.
     */
    public function resolve(Source $source): SourceAdapter
    {
        $adapterClass = $this->adapters[$source->adapter] ?? $source->adapter;

        if (! class_exists($adapterClass)) {
            throw new InvalidArgumentException("Adapter class [{$adapterClass}] for source [{$source->key}] does not exist.");
        }

        $instance = app($adapterClass);

        if (! $instance instanceof SourceAdapter) {
            throw new InvalidArgumentException("Adapter [{$adapterClass}] does not implement SourceAdapter interface.");
        }

        return $instance;
    }
}
