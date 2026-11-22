<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<int>
     */
    private function listingPrices(string $url): array
    {
        return collect($this->get($url)->viewData('page')['props']['listings']['data'])
            ->pluck('price')->sort()->values()->all();
    }

    private function currentSearchSaved(string $url): bool
    {
        return $this->get($url)->viewData('page')['props']['currentSearchSaved'];
    }

    public function test_the_search_description_is_sent_for_applied_filters(): void
    {
        // Act
        $response = $this->get('/?max_price=200000&min_bedrooms=2');

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('searchDescription', 'Up to £200,000 · 2+ beds'));
    }

    public function test_the_search_description_is_null_without_filters(): void
    {
        // Act
        $response = $this->get('/');

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('searchDescription', null));
    }

    public function test_a_listing_image_is_chosen_deterministically_from_the_id(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();
        $n = $listing->id % 5 + 1;

        // Act
        $response = $this->get(route('listings.show', $listing));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('listing.image_url', asset("images/listings/house-{$n}.webp")));
        $this->assertFileExists(public_path("images/listings/house-{$n}.webp"));
    }

    public function test_price_range_is_inclusive_at_both_ends(): void
    {
        // Arrange
        foreach ([90_000, 100_000, 150_000, 200_000, 250_000] as $price) {
            Listing::factory()->live()->create(['price' => $price]);
        }

        // Act
        $prices = $this->listingPrices('/?min_price=100000&max_price=200000&per_page=100');

        // Assert
        $this->assertSame([100_000, 150_000, 200_000], $prices);
    }

    public function test_min_price_alone_is_a_lower_bound(): void
    {
        // Arrange
        foreach ([90_000, 100_000, 250_000] as $price) {
            Listing::factory()->live()->create(['price' => $price]);
        }

        // Act
        $prices = $this->listingPrices('/?min_price=100000');

        // Assert
        $this->assertSame([100_000, 250_000], $prices);
    }

    public function test_bedroom_range_is_inclusive_at_both_ends(): void
    {
        // Arrange
        foreach ([1, 2, 3, 4, 5] as $beds) {
            Listing::factory()->live()->create(['bedrooms' => $beds, 'price' => $beds * 1000]);
        }

        // Act
        $prices = $this->listingPrices('/?min_bedrooms=2&max_bedrooms=4');

        // Assert
        $this->assertSame([2000, 3000, 4000], $prices);
    }

    public function test_several_regions_and_types_match_any_of_them(): void
    {
        // Arrange
        $liverpool = Branch::factory()->create(['region' => 'Liverpool']);
        $newcastle = Branch::factory()->create(['region' => 'Newcastle']);
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        Listing::factory()->live()->for($liverpool)->create(['property_type' => PropertyType::Flat, 'price' => 1]);
        Listing::factory()->live()->for($newcastle)->create(['property_type' => PropertyType::Detached, 'price' => 2]);
        Listing::factory()->live()->for($leeds)->create(['property_type' => PropertyType::Flat, 'price' => 3]);
        Listing::factory()->live()->for($liverpool)->create(['property_type' => PropertyType::Bungalow, 'price' => 4]);

        // Act
        $results = [
            'regions' => $this->listingPrices('/?region=Liverpool,Newcastle'),
            'one type' => $this->listingPrices('/?property_type=flat'),
            'two types' => $this->listingPrices('/?property_type=flat,detached'),
            'regions and types' => $this->listingPrices('/?region=Liverpool,Newcastle&property_type=flat,detached'),
        ];

        // Assert
        $this->assertSame([
            'regions' => [1, 2, 4],
            'one type' => [1, 3],
            'two types' => [1, 2, 3],
            'regions and types' => [1, 2],
        ], $results);
    }

    public function test_array_style_multi_values_work_too(): void
    {
        // Arrange
        $liverpool = Branch::factory()->create(['region' => 'Liverpool']);
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        Listing::factory()->live()->for($liverpool)->create(['price' => 1]);
        Listing::factory()->live()->for($leeds)->create(['price' => 2]);

        // Act
        $prices = $this->listingPrices('/?region[]=Liverpool');

        // Assert
        $this->assertSame([1], $prices);
    }

    public function test_old_single_value_links_still_work(): void
    {
        // Arrange
        $branch = Branch::factory()->create(['region' => 'Leeds']);
        Listing::factory()->live()->for($branch)->create(['property_type' => PropertyType::Terraced, 'bedrooms' => 3, 'price' => 150_000]);
        Listing::factory()->live()->create(['property_type' => PropertyType::Flat, 'bedrooms' => 1, 'price' => 150_000]);

        // Act
        $response = $this->get('/?region=Leeds&property_type=terraced&min_bedrooms=3&max_price=200000');

        // Assert
        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('listings.data', 1));
    }

    public function test_region_casing_is_normalised_to_the_branch_region(): void
    {
        // Arrange
        $branch = Branch::factory()->create(['region' => 'Liverpool']);
        Listing::factory()->live()->for($branch)->create();

        // Act
        $response = $this->get('/?region=liverpool');

        // Assert
        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('listings.data', 1)
                ->where('filters.region', ['Liverpool'])
            );
    }

    public function test_echoed_filters_are_normalised(): void
    {
        // Arrange
        Branch::factory()->create(['region' => 'Liverpool']);
        Branch::factory()->create(['region' => 'Newcastle']);

        // Act
        $response = $this->get('/?region=Newcastle,,Liverpool,Newcastle&property_type=flat,detached&min_price=&max_price=300000&min_bedrooms=2&page=1&per_page=5');

        // Assert
        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters', [
                    'min_price' => null,
                    'max_price' => 300000,
                    'min_bedrooms' => 2,
                    'max_bedrooms' => null,
                    'region' => ['Liverpool', 'Newcastle'],
                    'property_type' => ['detached', 'flat'],
                ])
            );
    }

    public function test_blank_filters_are_absent(): void
    {
        // Arrange
        Listing::factory(2)->live()->create();

        // Act
        $response = $this->get('/?min_price=&max_price=&min_bedrooms=&max_bedrooms=&property_type=&region=');

        // Assert
        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('listings.data', 2)
                ->where('filters.region', [])
                ->where('filters.min_price', null)
            );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function badLinks(): array
    {
        return [
            'unknown region' => ['region=Atlantis', 'region'],
            'unknown type' => ['property_type=flat,castle', 'property_type'],
            'negative price' => ['min_price=-1', 'min_price'],
            'decimal price' => ['max_price=1.5', 'max_price'],
            'huge price' => ['max_price=999999999999', 'max_price'],
            'min above max price' => ['min_price=300&max_price=200', 'min_price'],
            'min above max bedrooms' => ['min_bedrooms=4&max_bedrooms=2', 'min_bedrooms'],
            'too many bedrooms' => ['max_bedrooms=21', 'max_bedrooms'],
            'nested garbage' => ['region[][x]=1', 'region'],
        ];
    }

    /**
     * A GET page can not send the user "back" to the same bad URL, so a bad link
     * redirects to the same page minus the bad filters, with the error shown.
     */
    #[DataProvider('badLinks')]
    public function test_a_bad_link_lands_on_a_working_page_with_an_error(string $query, string $field): void
    {
        // Arrange
        Branch::factory()->create(['region' => 'Liverpool']);
        Listing::factory(2)->live()->create();

        // Act
        $response = $this->followingRedirects()->get("/?{$query}");

        // Assert
        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Listings/Index')
                ->has("errors.{$field}")
            );
    }

    public function test_a_bad_filter_is_dropped_so_the_page_still_lists_homes(): void
    {
        // Arrange
        Listing::factory(2)->live()->create();

        // Act
        $response = $this->followingRedirects()->get('/?region=Atlantis');

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('listings.data', 2)
            ->where('filters.region', [])
        );
    }

    public function test_a_bad_link_keeps_the_valid_filters(): void
    {
        // Act
        $response = $this->get('/?region=Atlantis&max_price=200000');

        // Assert
        $response->assertRedirect('/?max_price=200000')
            ->assertSessionHasErrors('region');
    }

    public function test_index_provides_options_for_the_filter_form(): void
    {
        // Arrange
        Branch::factory()->create(['region' => 'Leeds']);
        Branch::factory()->create(['region' => 'Leeds']);
        Branch::factory()->create(['region' => 'Bristol']);

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('regionOptions', ['Bristol', 'Leeds'])
                ->has('propertyTypes', count(PropertyType::cases()))
                ->has('priceOptions.0', fn (AssertableInertia $option) => $option->hasAll('value', 'label'))
                ->has('bedroomOptions.0', fn (AssertableInertia $option) => $option->hasAll('value', 'label'))
            );
    }

    public function test_current_search_saved_is_true_only_for_an_equal_saved_search(): void
    {
        // Arrange
        $user = User::factory()->create();
        Branch::factory()->create(['region' => 'Liverpool']);
        Branch::factory()->create(['region' => 'Newcastle']);
        SavedSearch::factory()->for($user)->inRegions(['Liverpool', 'Newcastle'])->create();

        // Act
        $results = [
            'equal search' => $this->currentSearchSaved('/?region=Newcastle,Liverpool&min_price=100000&max_price=200000&page=2'),
            'fewer regions' => $this->currentSearchSaved('/?region=Liverpool&min_price=100000&max_price=200000'),
            'fewer bounds' => $this->currentSearchSaved('/?region=Newcastle,Liverpool&min_price=100000'),
            'no filters' => $this->currentSearchSaved('/'),
        ];

        // Assert
        $this->assertSame([
            'equal search' => true,
            'fewer regions' => false,
            'fewer bounds' => false,
            'no filters' => false,
        ], $results);
    }

    public function test_current_search_saved_ignores_other_users_searches(): void
    {
        // Arrange
        User::factory()->create();
        $other = User::factory()->create();
        SavedSearch::factory()->for($other)->create();

        // Act
        $response = $this->get('/?min_price=100000&max_price=200000');

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('currentSearchSaved', false));
    }
}
