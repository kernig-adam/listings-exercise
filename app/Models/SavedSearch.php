<?php

namespace App\Models;

use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Every criterion is optional and an empty one means "any": a null price or
 * bedroom bound is unbounded on that side, and a search with no region (or no
 * property type) rows matches every region (or type). The "at least one
 * criterion" rule is a validation concern, not a data one.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $min_price
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property int|null $max_bedrooms
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, SavedSearchRegion> $regions
 * @property-read Collection<int, SavedSearchPropertyType> $propertyTypes
 * @property-read Collection<int, Alert> $alerts
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /** Bounds abuse and the cost of matching every new listing against every search. */
    public const MAX_PER_USER = 20;

    // user_id is deliberately absent: it is always set from the authenticated user.
    protected $fillable = [
        'min_price',
        'max_price',
        'min_bedrooms',
        'max_bedrooms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_price' => 'integer',
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
            'max_bedrooms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SavedSearchRegion, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(SavedSearchRegion::class);
    }

    /**
     * @return HasMany<SavedSearchPropertyType, $this>
     */
    public function propertyTypes(): HasMany
    {
        return $this->hasMany(SavedSearchPropertyType::class);
    }

    /**
     * @return BelongsToMany<Alert, $this>
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class);
    }
}
