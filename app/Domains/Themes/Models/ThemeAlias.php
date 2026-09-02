<?php

namespace App\Domains\Themes\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $theme_id
 * @property string $alias
 * @property string $normalized_alias
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Theme $theme
 */
class ThemeAlias extends Model
{
    protected $table = 'theme_aliases';

    protected $fillable = [
        'theme_id',
        'alias',
        'normalized_alias',
    ];

    /**
     * @return BelongsTo<Theme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }
}
