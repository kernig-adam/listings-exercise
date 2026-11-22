<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Support\SearchCriteria;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The casts() method tells Eloquent how to hydrate these attributes, but static
 * analysis can't infer that from a string map — these annotations do.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $reference
 * @property string $address_line_1
 * @property string $city
 * @property string $postcode
 * @property int $price
 * @property int $bedrooms
 * @property int $bathrooms
 * @property PropertyType $property_type
 * @property ListingStatus $status
 * @property Carbon|null $listed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 */
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'reference',
        'address_line_1',
        'city',
        'postcode',
        'price',
        'bedrooms',
        'bathrooms',
        'property_type',
        'status',
        'listed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'property_type' => PropertyType::class,
            'status' => ListingStatus::class,
            'listed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Only listings that are currently on the market.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', ListingStatus::Live);
    }

    /**
     * The one definition of "this listing matches a search", shared by the
     * listings page and alert matching. Empty criteria leave that dimension open.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeMatching(Builder $query, SearchCriteria $criteria): void
    {
        $query
            ->when($criteria->minPrice !== null, fn (Builder $q) => $q->where('price', '>=', $criteria->minPrice))
            ->when($criteria->maxPrice !== null, fn (Builder $q) => $q->where('price', '<=', $criteria->maxPrice))
            ->when($criteria->minBedrooms !== null, fn (Builder $q) => $q->where('bedrooms', '>=', $criteria->minBedrooms))
            ->when($criteria->maxBedrooms !== null, fn (Builder $q) => $q->where('bedrooms', '<=', $criteria->maxBedrooms))
            ->when($criteria->propertyTypes !== [], fn (Builder $q) => $q->whereIn('property_type', $criteria->propertyTypes))
            ->when($criteria->regions !== [], fn (Builder $q) => $q->whereHas(
                'branch',
                fn (Builder $branch) => $branch->whereIn('region', $criteria->regions),
            ));
    }
}
