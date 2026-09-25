<?php

namespace App\Domains\Themes\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * LLM-written, grounded summary of an entity's top themes (365d window). Derived
 * text only — built from theme counts and paraphrased contexts, never raw payloads.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $summary
 * @property array<int, string> $theme_notes
 * @property int $opinion_count
 * @property Carbon $generated_at
 */
class EntityThemeSummary extends Model
{
    protected $fillable = ['entity_id', 'summary', 'theme_notes', 'opinion_count', 'generated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme_notes' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
