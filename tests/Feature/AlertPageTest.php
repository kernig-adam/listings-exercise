<?php

namespace Tests\Feature;

use App\Enums\AlertReason;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AlertPageTest extends TestCase
{
    use RefreshDatabase;

    /** The demo user: the auth stub resolves the lowest user id. */
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_it_lists_the_users_alerts_newest_first(): void
    {
        // Arrange
        $old = Alert::factory()->for($this->user)->create(['updated_at' => now()->subDays(2)]);
        $new = Alert::factory()->for($this->user)->create(['updated_at' => now()->subDay()]);

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Alerts/Index')
            ->has('alertList.data', 2)
            ->where('alertList.data.0.id', $new->id)
            ->where('alertList.data.1.id', $old->id));
    }

    public function test_another_users_alerts_are_never_visible(): void
    {
        // Arrange
        Alert::factory()->for($this->user)->create();
        Alert::factory()->create();

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->has('alertList.data', 1));
    }

    public function test_another_users_alerts_are_never_marked_read(): void
    {
        // Arrange
        $other = Alert::factory()->create();
        Alert::factory()->for($this->user)->create();

        // Act
        $this->get(route('alerts.index'));

        // Assert
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_an_alert_exposes_its_reason_and_listing(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();
        Alert::factory()->for($this->user)->backOnMarket()->create(['listing_id' => $listing->id]);

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('alertList.data.0.reason', AlertReason::BackOnMarket->value)
            ->where('alertList.data.0.reason_label', 'Back on the market')
            ->where('alertList.data.0.listing.id', $listing->id)
            ->where('alertList.data.0.is_available', true)
            ->has('alertList.data.0.alerted_at'));
    }

    public function test_a_listing_that_is_no_longer_live_is_flagged_unavailable(): void
    {
        // Arrange
        $listing = Listing::factory()->sold()->create();
        Alert::factory()->for($this->user)->create(['listing_id' => $listing->id]);

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('alertList.data.0.is_available', false));
    }

    public function test_an_alert_lists_the_descriptions_of_its_matched_searches(): void
    {
        // Arrange
        $search = SavedSearch::factory()->for($this->user)->create(['min_price' => null, 'max_price' => 200_000]);
        $alert = Alert::factory()->for($this->user)->create();
        $alert->savedSearches()->attach($search);

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('alertList.data.0.matched', ['Up to £200,000'])
            ->where('alertList.data.0.matched_deleted', false));
    }

    public function test_an_alert_whose_searches_were_all_deleted_is_still_listed_and_explained(): void
    {
        // Arrange
        $search = SavedSearch::factory()->for($this->user)->create();
        $alert = Alert::factory()->for($this->user)->create();
        $alert->savedSearches()->attach($search);
        $search->delete();

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('alertList.data', 1)
            ->where('alertList.data.0.matched', [])
            ->where('alertList.data.0.matched_deleted', true));
    }

    public function test_viewing_page_one_shows_its_alerts_as_new(): void
    {
        // Arrange
        foreach (range(1, 16) as $i) {
            Alert::factory()->for($this->user)->create(['updated_at' => now()->subMinutes($i)]);
        }

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('alertList.data', 15)
            ->where('alertList.data.0.is_new', true)
            ->where('alertList.data.14.is_new', true));
    }

    public function test_viewing_page_one_marks_only_that_page_read(): void
    {
        // Arrange
        foreach (range(1, 16) as $i) {
            Alert::factory()->for($this->user)->create(['updated_at' => now()->subMinutes($i)]);
        }
        $oldest = Alert::query()->orderBy('updated_at')->firstOrFail();

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('alerts.unread', 1));
        $this->assertSame(1, Alert::query()->unread()->count());
        $this->assertNull($oldest->fresh()->read_at);
    }

    public function test_marking_read_does_not_reorder_the_list(): void
    {
        // Arrange
        $newer = Alert::factory()->for($this->user)->create(['updated_at' => now()->subMinute()]);
        Alert::factory()->for($this->user)->create(['updated_at' => now()->subHour()]);
        $this->get(route('alerts.index'));

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('alertList.data.0.id', $newer->id)
            ->where('alertList.data.0.is_new', false));
    }

    public function test_the_unread_count_is_shared_on_every_page(): void
    {
        // Arrange
        Alert::factory()->for($this->user)->count(2)->create();
        Alert::factory()->for($this->user)->read()->create();
        Alert::factory()->create();

        // Act
        $response = $this->get(route('listings.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('alerts.unread', 2));
    }

    public function test_the_unread_count_is_zero_without_a_user(): void
    {
        // Arrange
        $this->user->delete();

        // Act
        $response = $this->get(route('listings.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('alerts.unread', 0)
            ->where('auth.user', null));
    }
}
