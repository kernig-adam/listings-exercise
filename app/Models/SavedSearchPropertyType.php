<?php

namespace App\Models;

use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One property type a saved search covers; no rows means "any type".
 *
 * @property int $id
 * @property int $saved_search_id
 * @property PropertyType $property_type
 * @property-read SavedSearch $savedSearch
 */
class SavedSearchPropertyType extends Model
{
    public $timestamps = false;

    protected $fillable = ['property_type'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['property_type' => PropertyType::class];
    }

    /**
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }
}
