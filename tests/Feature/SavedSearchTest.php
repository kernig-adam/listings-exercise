<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    /** The demo user: the auth stub resolves the lowest user id. */
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Branch::factory()->create(['region' => 'Liverpool']);
        Branch::factory()->create(['region' => 'Newcastle']);
    }

    public function test_a_search_is_saved_from_validated_criteria(): void
    {
        // Act
        $response = $this->post(route('saved-searches.store'), [
            'min_price' => '100000',
            'max_price' => '200000',
            'min_bedrooms' => '2',
            'min_bathrooms' => '1',
            'max_bathrooms' => '2',
            'region' => 'newcastle,Liverpool',
            'property_type' => 'flat,detached',
        ]);

        // Assert
        $response->assertRedirect()
            ->assertSessionHas('status', 'Search saved.')
            ->assertSessionHasNoErrors();
        $search = $this->user->savedSearches()->with(['regions', 'propertyTypes'])->sole();
        $this->assertSame(100_000, $search->min_price);
        $this->assertSame(200_000, $search->max_price);
        $this->assertSame(2, $search->min_bedrooms);
        $this->assertNull($search->max_bedrooms);
        $this->assertSame(1, $search->min_bathrooms);
        $this->assertSame(2, $search->max_bathrooms);
        $this->assertEqualsCanonicalizing(['Liverpool', 'Newcastle'], $search->regions->pluck('region')->all());
        $this->assertEqualsCanonicalizing(
            [PropertyType::Flat, PropertyType::Detached],
            $search->propertyTypes->pluck('property_type')->all(),
        );
    }

    public function test_array_style_criteria_are_accepted_too(): void
    {
        // Act
        $response = $this->post(route('saved-searches.store'), ['region' => ['Liverpool'], 'property_type' => ['flat']]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $this->user->savedSearches()->count());
    }

    public function test_user_id_cannot_be_set_from_input(): void
    {
        // Arrange
        $other = User::factory()->create();

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => 100000, 'user_id' => $other->id]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $this->user->savedSearches()->count());
        $this->assertSame(0, $other->savedSearches()->count());
    }

    public function test_an_empty_search_is_rejected(): void
    {
        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => '', 'region' => '', 'page' => 3]);

        // Assert
        $response->assertSessionHasErrors(['criteria' => 'Choose at least one filter before saving a search.']);
        $this->assertSame(0, SavedSearch::query()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCriteria(): array
    {
        return [
            'min above max price' => [['min_price' => 300, 'max_price' => 200], 'min_price'],
            'min above max bedrooms' => [['min_bedrooms' => 4, 'max_bedrooms' => 2], 'min_bedrooms'],
            'min above max bathrooms' => [['min_bathrooms' => 3, 'max_bathrooms' => 1], 'min_bathrooms'],
            'negative bathrooms' => [['max_bathrooms' => -1], 'max_bathrooms'],
            'too many bathrooms' => [['max_bathrooms' => 11], 'max_bathrooms'],
            'decimal bathrooms' => [['min_bathrooms' => 1.5], 'min_bathrooms'],
            'unknown type' => [['property_type' => 'castle'], 'property_type'],
            'unknown region' => [['region' => 'Atlantis'], 'region'],
            'negative price' => [['min_price' => -5], 'min_price'],
            'negative bedrooms' => [['max_bedrooms' => -1], 'max_bedrooms'],
            'non-numeric price' => [['max_price' => 'lots'], 'max_price'],
            'decimal bedrooms' => [['min_bedrooms' => 2.5], 'min_bedrooms'],
            'absurd price' => [['max_price' => 999_999_999_999], 'max_price'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidCriteria')]
    public function test_invalid_criteria_are_rejected(array $input, string $field): void
    {
        // Act
        $response = $this->postJson(route('saved-searches.store'), $input);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors($field);
        $this->assertSame(0, SavedSearch::query()->count());
    }

    public function test_a_duplicate_is_rejected_after_normalising(): void
    {
        // Arrange
        $this->post(route('saved-searches.store'), ['region' => 'Newcastle,Liverpool', 'min_price' => 100000]);

        // Act
        $response = $this->post(route('saved-searches.store'), [
            'region' => 'Liverpool,Newcastle,Liverpool',
            'min_price' => '100000',
            'max_price' => '',
            'page' => 2,
        ]);

        // Assert
        $response->assertSessionHasErrors(['criteria' => 'You already have this search saved.']);
        $this->assertSame(1, $this->user->savedSearches()->count());
    }

    public function test_the_bathroom_message_is_friendly(): void
    {
        // Act
        $response = $this->postJson(route('saved-searches.store'), ['min_bathrooms' => 3, 'max_bathrooms' => 1]);

        // Assert
        $response->assertJsonValidationErrors(['min_bathrooms' => 'The minimum bathrooms cannot be more than the maximum.']);
    }

    public function test_the_same_bathrooms_make_a_duplicate(): void
    {
        // Arrange
        $this->post(route('saved-searches.store'), ['region' => 'Liverpool', 'min_bathrooms' => 2]);

        // Act
        $response = $this->post(route('saved-searches.store'), ['region' => 'Liverpool', 'min_bathrooms' => '2']);

        // Assert
        $response->assertSessionHasErrors(['criteria' => 'You already have this search saved.']);
        $this->assertSame(1, $this->user->savedSearches()->count());
    }

    public function test_different_bathrooms_are_not_a_duplicate(): void
    {
        // Arrange
        $this->post(route('saved-searches.store'), ['region' => 'Liverpool', 'min_bathrooms' => 2]);

        // Act
        $response = $this->post(route('saved-searches.store'), ['region' => 'Liverpool', 'min_bathrooms' => 3]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertSame(2, $this->user->savedSearches()->count());
    }

    public function test_a_different_search_is_not_a_duplicate(): void
    {
        // Arrange
        $this->post(route('saved-searches.store'), ['region' => 'Liverpool']);

        // Act
        $response = $this->post(route('saved-searches.store'), ['region' => 'Liverpool,Newcastle']);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertSame(2, $this->user->savedSearches()->count());
    }

    public function test_another_users_identical_search_is_not_a_duplicate(): void
    {
        // Arrange
        $other = User::factory()->create();
        SavedSearch::factory()->for($other)->create(['min_price' => 100000, 'max_price' => null]);

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => 100000]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $this->user->savedSearches()->count());
    }

    public function test_the_twenty_search_cap_is_enforced_with_a_friendly_message(): void
    {
        // Arrange
        foreach (range(1, 20) as $price) {
            SavedSearch::factory()->for($this->user)->create(['min_price' => $price, 'max_price' => null]);
        }

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => 999]);

        // Assert
        $response->assertSessionHasErrors(['criteria' => 'You have 20 saved searches. Delete one to save another.']);
        $this->assertSame(20, $this->user->savedSearches()->count());
    }

    public function test_the_cap_counts_only_the_users_own_searches(): void
    {
        // Arrange
        SavedSearch::factory(20)->for(User::factory()->create())->create();

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => 999]);

        // Assert
        $response->assertSessionHasNoErrors();
    }

    public function test_saving_is_throttled_with_a_friendly_message(): void
    {
        // Arrange
        foreach (range(1, 10) as $price) {
            $this->post(route('saved-searches.store'), ['min_price' => $price]);
        }

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => 11]);

        // Assert
        $response->assertRedirect()
            ->assertSessionHasErrors('criteria');
        $this->assertSame(10, $this->user->savedSearches()->count());
    }

    public function test_the_index_lists_only_the_users_searches_newest_first(): void
    {
        // Arrange
        $other = User::factory()->create();
        SavedSearch::factory()->for($other)->create();
        $old = SavedSearch::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
        $new = SavedSearch::factory()->for($this->user)->create(['created_at' => now()]);

        // Act
        $response = $this->get(route('saved-searches.index'));

        // Assert
        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches.data', 2)
                ->where('savedSearches.data.0.id', $new->id)
                ->where('savedSearches.data.1.id', $old->id)
                ->where('savedSearchLimit', 20)
            );
    }

    public function test_the_index_paginates_at_fifteen(): void
    {
        // Arrange
        SavedSearch::factory(18)->for($this->user)->create();

        // Act
        $response = $this->get(route('saved-searches.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->has('savedSearches.data', 15));
    }

    public function test_the_resource_exposes_criteria_description_and_view_url(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)
            ->inRegions(['Newcastle', 'Liverpool'])
            ->ofTypes([PropertyType::Flat, PropertyType::Detached])
            ->create(['min_price' => 100_000, 'max_price' => 200_000, 'min_bedrooms' => 2, 'max_bedrooms' => 4]);

        // Act
        $response = $this->get(route('saved-searches.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('savedSearches.data.0', fn (AssertableInertia $search) => $search
                ->hasAll('id', 'criteria', 'description', 'view_url', 'created_at')
                ->where('description', 'Liverpool & Newcastle · Detached homes & Flats · £100,000–£200,000 · 2–4 beds')
                ->where('criteria.region', ['Liverpool', 'Newcastle'])
                ->where('criteria.property_type', ['detached', 'flat'])
                ->where('criteria.min_price', 100000)
                ->where('view_url', route('listings.index', [
                    'region' => 'Liverpool,Newcastle',
                    'property_type' => 'detached,flat',
                    'min_price' => 100000,
                    'max_price' => 200000,
                    'min_bedrooms' => 2,
                    'max_bedrooms' => 4,
                ]))
            )
        );
    }

    public function test_the_view_url_round_trips_to_the_same_saved_search(): void
    {
        // Arrange
        $this->post(route('saved-searches.store'), ['region' => 'Liverpool,Newcastle', 'property_type' => 'flat', 'min_bedrooms' => 3]);
        $url = $this->get(route('saved-searches.index'))->viewData('page')['props']['savedSearches']['data'][0]['view_url'];

        // Act
        $response = $this->get($url);

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('currentSearchSaved', true));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function descriptions(): array
    {
        return [
            'up to' => [['min_price' => null, 'max_price' => 200_000], 'Up to £200,000'],
            'from' => [['min_price' => 100_000, 'max_price' => null], 'From £100,000'],
            'range' => [['min_price' => 100_000, 'max_price' => 200_000], '£100,000–£200,000'],
            'any price' => [['min_price' => null, 'max_price' => null, 'min_bedrooms' => 3], 'Any price · 3+ beds'],
            'bedroom range' => [['min_price' => null, 'max_price' => null, 'min_bedrooms' => 2, 'max_bedrooms' => 3], 'Any price · 2–3 beds'],
            'exact beds' => [['min_price' => null, 'max_price' => null, 'min_bedrooms' => 3, 'max_bedrooms' => 3], 'Any price · 3 beds'],
            'single bed' => [['min_price' => null, 'max_price' => null, 'min_bedrooms' => 1, 'max_bedrooms' => 1], 'Any price · 1 bed'],
            'bath minimum' => [['min_price' => null, 'max_price' => null, 'min_bathrooms' => 2], 'Any price · 2+ baths'],
            'bath range' => [['min_price' => null, 'max_price' => null, 'min_bathrooms' => 1, 'max_bathrooms' => 2], 'Any price · 1–2 baths'],
            'exact baths' => [['min_price' => null, 'max_price' => null, 'min_bathrooms' => 2, 'max_bathrooms' => 2], 'Any price · 2 baths'],
            'single bath' => [['min_price' => null, 'max_price' => null, 'min_bathrooms' => 1, 'max_bathrooms' => 1], 'Any price · 1 bath'],
            'max baths' => [['min_price' => null, 'max_price' => null, 'max_bathrooms' => 2], 'Any price · Up to 2 baths'],
            'beds then baths' => [['min_price' => null, 'max_price' => null, 'min_bedrooms' => 3, 'min_bathrooms' => 2], 'Any price · 3+ beds · 2+ baths'],
            'max beds' => [['min_price' => null, 'max_price' => null, 'max_bedrooms' => 2], 'Any price · Up to 2 beds'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('descriptions')]
    public function test_description_shapes(array $attributes, string $expected): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create($attributes);

        // Act
        $response = $this->get(route('saved-searches.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearches.data.0.description', $expected));
    }

    public function test_description_lists_regions_and_types_in_a_consistent_order(): void
    {
        // Arrange
        Branch::factory()->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($this->user)->anyCriteria()
            ->inRegions(['Newcastle', 'Leeds', 'Liverpool'])
            ->ofTypes([PropertyType::Terraced, PropertyType::Bungalow, PropertyType::SemiDetached, PropertyType::Flat])
            ->create();

        // Act
        $response = $this->get(route('saved-searches.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where(
            'savedSearches.data.0.description',
            'Leeds, Liverpool & Newcastle · Bungalows, Flats, Semi-detached homes & Terraced homes · Any price',
        ));
    }

    public function test_a_search_is_deleted(): void
    {
        // Arrange
        $search = SavedSearch::factory()->for($this->user)->create();

        // Act
        $response = $this->delete(route('saved-searches.destroy', $search->id));

        // Assert
        $response->assertRedirect()
            ->assertSessionHas('status', 'Search deleted.');
        $this->assertModelMissing($search);
    }

    public function test_deleting_the_last_search_on_a_page_returns_to_the_new_last_page(): void
    {
        // Arrange
        SavedSearch::factory(16)->for($this->user)->create();
        $onlyOnPageTwo = $this->user->savedSearches()->orderBy('created_at')->orderBy('id')->firstOrFail();

        // Act
        $response = $this->delete(
            route('saved-searches.destroy', $onlyOnPageTwo->id),
            [],
            ['Referer' => route('saved-searches.index', ['page' => 2])],
        );

        // Assert
        $response->assertRedirect(route('saved-searches.index'))
            ->assertSessionHas('status', 'Search deleted.');
    }

    public function test_deleting_a_search_keeps_the_page_while_it_still_has_searches(): void
    {
        // Arrange
        SavedSearch::factory(17)->for($this->user)->create();
        $search = $this->user->savedSearches()->firstOrFail();

        // Act
        $response = $this->delete(
            route('saved-searches.destroy', $search->id),
            [],
            ['Referer' => route('saved-searches.index', ['page' => 2])],
        );

        // Assert
        $response->assertRedirect(route('saved-searches.index', ['page' => 2]));
    }

    public function test_a_stale_page_is_clamped_to_the_last_page_with_searches(): void
    {
        // Arrange
        SavedSearch::factory(2)->for($this->user)->create();
        $search = $this->user->savedSearches()->firstOrFail();

        // Act
        $response = $this->delete(
            route('saved-searches.destroy', $search->id),
            [],
            ['Referer' => route('saved-searches.index', ['page' => 9])],
        );

        // Assert
        $response->assertRedirect(route('saved-searches.index'));
    }

    public function test_the_index_shares_the_total_count_across_pages(): void
    {
        // Arrange
        SavedSearch::factory(18)->for($this->user)->create();
        SavedSearch::factory()->for(User::factory()->create())->create();

        // Act
        $response = $this->get(route('saved-searches.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearchCount', 18));
    }

    public function test_deleting_a_search_keeps_its_alerts(): void
    {
        // Arrange
        $search = SavedSearch::factory()->for($this->user)->create();
        $alert = Alert::factory()->create(['user_id' => $this->user->id]);
        $alert->savedSearches()->attach($search);

        // Act
        $this->delete(route('saved-searches.destroy', $search->id));

        // Assert
        $this->assertModelExists($alert);
    }

    public function test_another_users_search_cannot_be_deleted(): void
    {
        // Arrange
        $search = SavedSearch::factory()->for(User::factory()->create())->create();

        // Act
        $response = $this->delete(route('saved-searches.destroy', $search->id));

        // Assert
        $response->assertNotFound();
        $this->assertModelExists($search);
    }

    public function test_a_missing_search_is_not_found(): void
    {
        // Act
        $response = $this->delete(route('saved-searches.destroy', 999));

        // Assert
        $response->assertNotFound();
    }

    public function test_flash_status_is_shared_with_the_page(): void
    {
        // Arrange
        $this->withSession(['status' => 'Search saved.']);

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('flash.status', 'Search saved.'));
    }
}
