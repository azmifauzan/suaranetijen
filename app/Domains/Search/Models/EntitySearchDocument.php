<?php

namespace App\Domains\Search\Models;

use App\Domains\Entities\Models\Entity;
use Database\Factories\EntitySearchDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entity_id
 * @property string|null $description_text
 * @property string|null $theme_text
 * @property string|null $spec_text
 * @property string|null $summary_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Entity $entity
 */
class EntitySearchDocument extends Model
{
    /** @use HasFactory<EntitySearchDocumentFactory> */
    use HasFactory;

    protected $table = 'entity_search_documents';

    protected $fillable = [
        'entity_id',
        'description_text',
        'theme_text',
        'spec_text',
        'summary_text',
    ];

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    protected static function newFactory(): EntitySearchDocumentFactory
    {
        return EntitySearchDocumentFactory::new();
    }
}
