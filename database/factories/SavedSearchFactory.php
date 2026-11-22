<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'min_price' => 100000,
            'max_price' => 200000,
            'min_bedrooms' => null,
            'max_bedrooms' => null,
        ];
    }

    /**
     * Every bound null: with no regions or types either, it matches everything.
     */
    public function anyCriteria(): static
    {
        return $this->state(fn () => [
            'min_price' => null,
            'max_price' => null,
            'min_bedrooms' => null,
            'max_bedrooms' => null,
        ]);
    }

    /**
     * @param  list<string>  $regions
     */
    public function inRegions(array $regions): static
    {
        return $this->afterCreating(function (SavedSearch $search) use ($regions) {
            foreach ($regions as $region) {
                $search->regions()->create(['region' => $region]);
            }
        });
    }

    /**
     * @param  list<PropertyType>  $types
     */
    public function ofTypes(array $types): static
    {
        return $this->afterCreating(function (SavedSearch $search) use ($types) {
            foreach ($types as $type) {
                $search->propertyTypes()->create(['property_type' => $type]);
            }
        });
    }
}
