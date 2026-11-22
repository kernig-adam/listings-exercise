<?php

namespace App\Models;

use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Every criterion is optional and an empty one means "any": a null price,
 * bedroom or bathroom bound is unbounded on that side, and a search with no region (or no
 * property type) rows matches every region (or type). The "at least one
 * criterion" rule is a validation concern, not a data one.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $min_price
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property int|null $max_bedrooms
 * @property int|null $min_bathrooms
 * @property int|null $max_bathrooms
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
        'min_bathrooms',
        'max_bathrooms',
    ];

    /**
     * Bounds are integers so they compare numerically against listings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_price' => 'integer',
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
            'max_bedrooms' => 'integer',
            'min_bathrooms' => 'integer',
            'max_bathrooms' => 'integer',
        ];
    }

    /**
     * The buyer who owns this search.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The regions this search covers; none means any region.
     *
     * @return HasMany<SavedSearchRegion, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(SavedSearchRegion::class);
    }

    /**
     * The property types this search covers; none means any type.
     *
     * @return HasMany<SavedSearchPropertyType, $this>
     */
    public function propertyTypes(): HasMany
    {
        return $this->hasMany(SavedSearchPropertyType::class);
    }

    /**
     * The alerts this search has produced.
     *
     * @return BelongsToMany<Alert, $this>
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class);
    }

    /**
     * The inverse of Listing::scopeMatching: the searches a given listing satisfies.
     * Null bounds and empty region/type sets are open; a test keeps the two in step.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatchingListing(Builder $query, Listing $listing): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('min_price')->orWhere('min_price', '<=', $listing->price))
            ->where(fn (Builder $q) => $q->whereNull('max_price')->orWhere('max_price', '>=', $listing->price))
            ->where(fn (Builder $q) => $q->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $q) => $q->whereNull('max_bedrooms')->orWhere('max_bedrooms', '>=', $listing->bedrooms))
            ->where(fn (Builder $q) => $q->whereNull('min_bathrooms')->orWhere('min_bathrooms', '<=', $listing->bathrooms))
            ->where(fn (Builder $q) => $q->whereNull('max_bathrooms')->orWhere('max_bathrooms', '>=', $listing->bathrooms))
            ->where(fn (Builder $q) => $q->whereDoesntHave('regions')->orWhereHas(
                'regions',
                fn (Builder $r) => $r->where('region', $listing->branch->region),
            ))
            ->where(fn (Builder $q) => $q->whereDoesntHave('propertyTypes')->orWhereHas(
                'propertyTypes',
                fn (Builder $t) => $t->where('property_type', $listing->property_type),
            ));
    }
}
