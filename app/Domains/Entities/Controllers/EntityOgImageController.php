<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\EntityOgImage;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntityOgImageController extends Controller
{
    public function __construct(protected EntityOgImage $ogImage) {}

    /**
     * Share card for an entity that clears the public threshold (docs/31 Fase 2).
     */
    public function show(string $slug): BinaryFileResponse
    {
        $entity = Entity::query()
            ->with('category')
            ->where('slug', $slug)
            ->where('searchable', true)
            ->active()
            ->firstOrFail();

        $snapshot = $this->ogImage->eligibleSnapshot($entity);
        abort_if($snapshot === null, 404);

        return response()->file($this->ogImage->path($entity, $snapshot), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
