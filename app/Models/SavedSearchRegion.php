<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One region a saved search covers. A tiny model keeps the join table typed and
 * eager-loadable; no rows for a search means "any region".
 *
 * @property int $id
 * @property int $saved_search_id
 * @property string $region
 * @property-read SavedSearch $savedSearch
 */
class SavedSearchRegion extends Model
{
    public $timestamps = false;

    protected $fillable = ['region'];

    /**
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }
}
