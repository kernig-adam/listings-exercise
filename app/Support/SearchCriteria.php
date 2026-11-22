<?php

namespace App\Support;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use App\Models\User;

/**
 * The one definition of "a search": built from the listings page's query string,
 * a saved search's rows or a save request, so the page, the duplicate check,
 * "Already saved" and the plain-words description can never disagree.
 *
 * A plain value object rather than a model because it carries behaviour
 * (normalising, comparing, describing) and is used before anything is stored.
 */
final class SearchCriteria
{
    /** Sensible upper bounds, enforced by the Form Requests. */
    public const MAX_PRICE = 100_000_000;

    public const MAX_BEDROOMS = 20;

    public const MAX_BATHROOMS = 10;

    /** Range-dropdown presets live here once; validation accepts any whole number in range. */
    private const PRICE_PRESETS = [
        50_000, 75_000, 100_000, 125_000, 150_000, 175_000, 200_000, 250_000, 300_000,
        350_000, 400_000, 500_000, 600_000, 750_000, 1_000_000,
    ];

    private const BEDROOM_PRESETS = [1, 2, 3, 4, 5, 6];

    private const BATHROOM_PRESETS = [1, 2, 3, 4];

    /**
     * Regions and types are sorted, unique sets so equal searches compare equal
     * whatever order or repetition they arrived in.
     *
     * @param  list<string>  $regions
     * @param  list<PropertyType>  $propertyTypes
     */
    public function __construct(
        public readonly ?int $minPrice = null,
        public readonly ?int $maxPrice = null,
        public readonly ?int $minBedrooms = null,
        public readonly ?int $maxBedrooms = null,
        public readonly ?int $minBathrooms = null,
        public readonly ?int $maxBathrooms = null,
        public readonly array $regions = [],
        public readonly array $propertyTypes = [],
    ) {}

    /**
     * Build from validated input. Blanks are absent; page and per_page are not
     * part of a search and are ignored.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $int = fn (string $key): ?int => filled($input[$key] ?? null) ? (int) $input[$key] : null;

        return self::normalised(
            $int('min_price'),
            $int('max_price'),
            $int('min_bedrooms'),
            $int('max_bedrooms'),
            $int('min_bathrooms'),
            $int('max_bathrooms'),
            array_map('strval', (array) ($input['region'] ?? [])),
            array_map(PropertyType::from(...), array_map('strval', (array) ($input['property_type'] ?? []))),
        );
    }

    /**
     * Builds criteria from a stored search. Load its regions and property types first, or this runs extra queries.
     */
    public static function fromSavedSearch(SavedSearch $search): self
    {
        return self::normalised(
            $search->min_price,
            $search->max_price,
            $search->min_bedrooms,
            $search->max_bedrooms,
            $search->min_bathrooms,
            $search->max_bathrooms,
            $search->regions->pluck('region')->all(),
            $search->propertyTypes->pluck('property_type')->all(),
        );
    }

    /**
     * Builds criteria with the region and type lists sorted and de-duplicated, so equal searches compare equal.
     *
     * @param  list<string>  $regions
     * @param  list<PropertyType>  $propertyTypes
     */
    private static function normalised(?int $minPrice, ?int $maxPrice, ?int $minBedrooms, ?int $maxBedrooms, ?int $minBathrooms, ?int $maxBathrooms, array $regions, array $propertyTypes): self
    {
        $regions = array_values(array_unique($regions));
        sort($regions);

        $propertyTypes = array_values(array_unique($propertyTypes, SORT_REGULAR));
        usort($propertyTypes, fn (PropertyType $a, PropertyType $b) => $a->value <=> $b->value);

        return new self($minPrice, $maxPrice, $minBedrooms, $maxBedrooms, $minBathrooms, $maxBathrooms, $regions, $propertyTypes);
    }

    /**
     * True when no criterion is set.
     */
    public function isEmpty(): bool
    {
        return $this->toQuery() === [];
    }

    /**
     * Whether two searches ask for exactly the same thing, whatever order the values came in.
     */
    public function equals(self $other): bool
    {
        return $this->toQuery() === $other->toQuery();
    }

    /**
     * Whether this exact search is already one of the user's saved searches.
     * A user holds at most SavedSearch::MAX_PER_USER, so comparing in PHP is cheap
     * and guarantees the same normaliser decides "duplicate" everywhere.
     */
    public function isSavedBy(User $user): bool
    {
        return $user->savedSearches()
            ->with(['regions', 'propertyTypes'])
            ->get()
            ->contains(fn (SavedSearch $saved) => $this->equals(self::fromSavedSearch($saved)));
    }

    /**
     * Columns for a SavedSearch row; regions and types go in their own tables.
     *
     * @return array{min_price: int|null, max_price: int|null, min_bedrooms: int|null, max_bedrooms: int|null, min_bathrooms: int|null, max_bathrooms: int|null}
     */
    public function attributes(): array
    {
        return [
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'min_bedrooms' => $this->minBedrooms,
            'max_bedrooms' => $this->maxBedrooms,
            'min_bathrooms' => $this->minBathrooms,
            'max_bathrooms' => $this->maxBathrooms,
        ];
    }

    /**
     * Query-string form for the listings page ("View homes"); only present
     * criteria, multi values comma-separated. Also the comparison key.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->regions !== []) {
            $query['region'] = implode(',', $this->regions);
        }
        if ($this->propertyTypes !== []) {
            $query['property_type'] = implode(',', array_map(fn (PropertyType $type) => $type->value, $this->propertyTypes));
        }
        foreach ($this->attributes() as $key => $value) {
            if ($value !== null) {
                $query[$key] = (string) $value;
            }
        }

        return $query;
    }

    /**
     * Echoed to the UI under the same keys as the form fields.
     *
     * @return array{min_price: int|null, max_price: int|null, min_bedrooms: int|null, max_bedrooms: int|null, min_bathrooms: int|null, max_bathrooms: int|null, region: list<string>, property_type: list<string>}
     */
    public function toArray(): array
    {
        return [
            ...$this->attributes(),
            'region' => $this->regions,
            'property_type' => array_map(fn (PropertyType $type) => $type->value, $this->propertyTypes),
        ];
    }

    /**
     * Plain words, always in the same order: regions, types, price, bedrooms, bathrooms.
     * Price is always present ("Any price") so a description is never blank.
     */
    public function description(): string
    {
        $parts = [];

        if ($this->regions !== []) {
            $parts[] = self::join($this->regions);
        }
        if ($this->propertyTypes !== []) {
            $parts[] = self::join(array_map(fn (PropertyType $type) => $type->pluralLabel(), $this->propertyTypes));
        }
        $parts[] = $this->priceDescription();

        $beds = $this->bedroomDescription();
        if ($beds !== null) {
            $parts[] = $beds;
        }

        $baths = $this->bathroomDescription();
        if ($baths !== null) {
            $parts[] = $baths;
        }

        return implode(' · ', $parts);
    }

    /**
     * Price choices for the dropdowns. Any whole number in range is still accepted.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function priceOptions(): array
    {
        return array_map(fn (int $price) => ['value' => $price, 'label' => self::money($price)], self::PRICE_PRESETS);
    }

    /**
     * Bedroom choices for the dropdowns.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function bedroomOptions(): array
    {
        return array_map(fn (int $beds) => ['value' => $beds, 'label' => (string) $beds], self::BEDROOM_PRESETS);
    }

    /**
     * Bathroom choices for the dropdowns.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function bathroomOptions(): array
    {
        return array_map(fn (int $baths) => ['value' => $baths, 'label' => (string) $baths], self::BATHROOM_PRESETS);
    }

    /**
     * The price part of the description. Never blank, so a description always says something.
     */
    private function priceDescription(): string
    {
        return match (true) {
            $this->minPrice !== null && $this->maxPrice !== null => self::money($this->minPrice).'–'.self::money($this->maxPrice),
            $this->maxPrice !== null => 'Up to '.self::money($this->maxPrice),
            $this->minPrice !== null => 'From '.self::money($this->minPrice),
            default => 'Any price',
        };
    }

    /**
     * The bedroom part of the description, or null if no bedroom limit is set.
     */
    private function bedroomDescription(): ?string
    {
        return match (true) {
            $this->minBedrooms !== null && $this->maxBedrooms === $this->minBedrooms => self::beds($this->minBedrooms),
            $this->minBedrooms !== null && $this->maxBedrooms !== null => $this->minBedrooms.'–'.self::beds($this->maxBedrooms),
            $this->minBedrooms !== null => $this->minBedrooms.'+ beds',
            $this->maxBedrooms !== null => 'Up to '.self::beds($this->maxBedrooms),
            default => null,
        };
    }

    /**
     * The bathroom part of the description, or null if no bathroom limit is set.
     */
    private function bathroomDescription(): ?string
    {
        return match (true) {
            $this->minBathrooms !== null && $this->maxBathrooms === $this->minBathrooms => self::baths($this->minBathrooms),
            $this->minBathrooms !== null && $this->maxBathrooms !== null => $this->minBathrooms.'–'.self::baths($this->maxBathrooms),
            $this->minBathrooms !== null => $this->minBathrooms.'+ baths',
            $this->maxBathrooms !== null => 'Up to '.self::baths($this->maxBathrooms),
            default => null,
        };
    }

    /**
     * "1 bath" / "2 baths".
     */
    private static function baths(int $count): string
    {
        return $count === 1 ? '1 bath' : "{$count} baths";
    }

    /**
     * "1 bed" / "2 beds".
     */
    private static function beds(int $count): string
    {
        return $count === 1 ? '1 bed' : "{$count} beds";
    }

    /**
     * A price with commas, e.g. £250,000.
     */
    private static function money(int $amount): string
    {
        return '£'.number_format($amount);
    }

    /**
     * "A", "A & B", "A, B & C".
     *
     * @param  list<string>  $items
     */
    private static function join(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' & '.$last;
    }
}
