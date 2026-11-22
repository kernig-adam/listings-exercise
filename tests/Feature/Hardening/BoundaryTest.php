<?php

namespace Tests\Feature\Hardening;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\SearchCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Edges of every limit: exactly at it is accepted, one past it is rejected.
 */
class BoundaryTest extends TestCase
{
    use CapturesLogs;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Zed Zebra', 'email' => 'zed@zebra.test']);
        Branch::factory()->create(['region' => 'Leeds']);
        $this->captureLogs();
    }

    public function test_the_twentieth_search_is_saved_and_the_twenty_first_is_rejected_without_a_log(): void
    {
        // Arrange
        // The throttle allows 10 writes a minute, so the first 19 are seeded directly.
        SavedSearch::factory()->for($this->user)->count(SavedSearch::MAX_PER_USER - 1)->create();
        $this->post(route('saved-searches.store'), ['min_price' => 500]);
        $this->captureLogs();

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => 501]);

        // Assert: refusing at the cap is ordinary feedback, so it is not logged (the throttle is the abuse signal).
        $response->assertSessionHasErrors('criteria');
        $this->assertSame(SavedSearch::MAX_PER_USER, $this->user->savedSearches()->count());
        $this->assertNotLogged('saved_search.created');
        $this->assertSame([], $this->logsNamed('Rate limit hit'));
    }

    public function test_another_users_searches_do_not_count_towards_the_cap(): void
    {
        // Arrange
        SavedSearch::factory()->for(User::factory()->create())->count(SavedSearch::MAX_PER_USER)->create();

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => 5]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $this->user->savedSearches()->count());
    }

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function limits(): array
    {
        $rows = [];
        $fields = [
            'price' => SearchCriteria::MAX_PRICE,
            'bedrooms' => SearchCriteria::MAX_BEDROOMS,
            'bathrooms' => SearchCriteria::MAX_BATHROOMS,
        ];
        foreach ($fields as $name => $max) {
            foreach (['min', 'max'] as $bound) {
                $field = "{$bound}_{$name}";
                $rows["{$field} zero"] = [$field, 0, true];
                $rows["{$field} at the maximum"] = [$field, $max, true];
                $rows["{$field} one past the maximum"] = [$field, $max + 1, false];
                $rows["{$field} below zero"] = [$field, -1, false];
            }
        }

        return $rows;
    }

    #[DataProvider('limits')]
    public function test_the_listings_page_accepts_a_limit_and_rejects_one_past_it(string $field, int $value, bool $valid): void
    {
        // Act
        $response = $this->get(route('listings.index', [$field => $value]));

        // Assert
        if ($valid) {
            $response->assertOk();
        } else {
            $response->assertRedirect()->assertSessionHasErrors($field);
        }
    }

    #[DataProvider('limits')]
    public function test_saving_accepts_a_limit_and_rejects_one_past_it(string $field, int $value, bool $valid): void
    {
        // Act
        $response = $this->post(route('saved-searches.store'), [$field => $value]);

        // Assert
        if ($valid) {
            $response->assertSessionHasNoErrors();
            $this->assertSame(1, SavedSearch::query()->count());
        } else {
            $response->assertSessionHasErrors($field);
            $this->assertSame(0, SavedSearch::query()->count());
        }
    }

    public function test_a_minimum_equal_to_the_maximum_is_accepted_and_above_it_is_rejected(): void
    {
        // Act
        $equal = $this->get(route('listings.index', ['min_price' => 5000, 'max_price' => 5000]));
        $above = $this->get(route('listings.index', ['min_price' => 5001, 'max_price' => 5000]));

        // Assert
        $equal->assertOk();
        $above->assertRedirect()->assertSessionHasErrors('min_price');
    }

    /**
     * @return array<string, array{array<string, int>}>
     */
    public static function badPaging(): array
    {
        return [
            'per_page zero' => [['per_page' => 0]],
            'per_page 101' => [['per_page' => 101]],
            'per_page negative' => [['per_page' => -5]],
            'page zero' => [['page' => 0]],
            'page negative' => [['page' => -1]],
        ];
    }

    /**
     * @param  array<string, int>  $query
     */
    #[DataProvider('badPaging')]
    public function test_bad_paging_is_rejected_and_not_logged_as_a_search(array $query): void
    {
        // Act
        $response = $this->get(route('listings.index', $query));

        // Assert
        $response->assertRedirect()->assertSessionHasErrors(array_key_first($query));
        $this->assertNotLogged('search.performed');
    }

    public function test_per_page_at_the_maximum_is_accepted(): void
    {
        // Act
        $response = $this->get(route('listings.index', ['per_page' => 100]));

        // Assert
        $response->assertOk();
    }

    public function test_a_page_beyond_the_last_is_an_empty_page_not_an_error(): void
    {
        // Arrange
        Listing::factory()->live()->count(3)->create();

        // Act
        $response = $this->get(route('listings.index', ['page' => 999]));

        // Assert
        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('listings.data', 0));
        $this->assertSame(3, $this->logsNamed('search.performed')[0]->context['result_count']);
        $this->assertSame(999, $this->logsNamed('search.performed')[0]->context['page']);
    }

    public function test_the_saved_search_and_alert_pages_beyond_the_last_are_empty_not_errors(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        Alert::factory()->for($this->user)->create();

        // Act
        $searches = $this->get(route('saved-searches.index', ['page' => 999]));
        $alerts = $this->get(route('alerts.index', ['page' => 999]));

        // Assert
        $searches->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('savedSearches.data', 0));
        $alerts->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('alertList.data', 0));
    }

    public function test_exactly_fifteen_alert_ids_are_accepted(): void
    {
        // Arrange
        $alerts = Alert::factory()->for($this->user)->count(15)->create();

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => $alerts->pluck('id')->all(), 'read' => true]);

        // Assert
        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, Alert::query()->unread()->count());
    }
}
