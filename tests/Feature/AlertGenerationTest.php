<?php

namespace Tests\Feature;

use App\Enums\AlertReason;
use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\SavedSearch;
use App\Models\User;
use App\Outbox\ListingWentLive;
use App\Support\SearchCriteria;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AlertGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->branch = Branch::factory()->create(['region' => 'Leeds']);
    }

    /**
     * A £150k, 3-bed flat in Leeds going live.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function goLive(array $attributes = []): Listing
    {
        return Listing::factory()->for($this->branch)->live()->create([
            'price' => 150_000,
            'bedrooms' => 3,
            'property_type' => PropertyType::Flat,
            ...$attributes,
        ]);
    }

    public function test_a_matching_user_gets_one_unread_new_listing_alert(): void
    {
        // Arrange
        $search = SavedSearch::factory()->for($this->user)->create();
        $listing = $this->goLive();

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $alert = Alert::query()->sole();
        $this->assertSame($this->user->id, $alert->user_id);
        $this->assertSame($listing->id, $alert->listing_id);
        $this->assertSame(AlertReason::NewListing, $alert->reason);
        $this->assertNull($alert->read_at);
        $this->assertSame([$search->id], $alert->savedSearches->pluck('id')->all());
    }

    public function test_non_matching_criteria_get_no_alert(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create(['min_price' => 300_000, 'max_price' => 400_000]);
        SavedSearch::factory()->for($this->user)->anyCriteria()->inRegions(['York'])->create();
        SavedSearch::factory()->for($this->user)->anyCriteria()->ofTypes([PropertyType::Detached])->create();
        $this->goLive();

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(0, Alert::query()->count());
    }

    public function test_each_user_is_alerted_only_for_their_own_matching_searches(): void
    {
        // Arrange
        $other = User::factory()->create();
        $third = User::factory()->create();
        SavedSearch::factory()->for($this->user)->create();
        SavedSearch::factory()->for($other)->create(['min_price' => 500_000, 'max_price' => 600_000]);
        SavedSearch::factory()->for($third)->anyCriteria()->create();
        $this->goLive();

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertEqualsCanonicalizing([$this->user->id, $third->id], Alert::query()->pluck('user_id')->all());
    }

    public function test_a_listing_no_longer_live_when_consumed_gets_no_alert(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        $listing = $this->goLive();
        $listing->update(['status' => ListingStatus::UnderOffer]);

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(0, Alert::query()->count());
    }

    public function test_a_missing_listing_gets_no_alert(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        $message = OutboxMessage::factory()->create([
            'payload' => ['listing_id' => 999_999, 'occurred_at' => now()->toIso8601String()],
        ]);

        // Act
        app(ListingWentLive::class)->handle($message);

        // Assert
        $this->assertSame(0, Alert::query()->count());
    }

    public function test_two_matching_searches_give_one_alert_linked_to_both(): void
    {
        // Arrange
        $a = SavedSearch::factory()->for($this->user)->create();
        $b = SavedSearch::factory()->for($this->user)->anyCriteria()->inRegions(['Leeds'])->create();
        $this->goLive();

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], Alert::query()->sole()->savedSearches->pluck('id')->all());
    }

    public function test_processing_the_same_message_twice_creates_no_duplicate(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        $this->goLive();
        $message = OutboxMessage::query()->sole();
        app(ListingWentLive::class)->handle($message);

        // Act
        app(ListingWentLive::class)->handle($message);

        // Assert
        $this->assertSame(1, Alert::query()->count());
        $this->assertSame(1, Alert::query()->sole()->savedSearches()->count());
    }

    public function test_processing_the_same_message_twice_changes_nothing(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        $this->goLive();
        $message = OutboxMessage::query()->sole();
        app(ListingWentLive::class)->handle($message);
        $before = Alert::query()->sole()->only(['id', 'updated_at', 'read_at']);
        $this->travel(5)->minutes();

        // Act
        app(ListingWentLive::class)->handle($message);

        // Assert
        $this->assertEquals($before, Alert::query()->sole()->only(['id', 'updated_at', 'read_at']));
    }

    public function test_reprocessing_does_not_add_an_alert_once_the_first_was_read(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        $this->goLive();
        $message = OutboxMessage::query()->sole();
        app(ListingWentLive::class)->handle($message);
        Alert::query()->sole()->update(['read_at' => now()]);

        // Act
        app(ListingWentLive::class)->handle($message);

        // Assert
        $this->assertSame(1, Alert::query()->count());
    }

    public function test_saving_a_search_creates_no_alerts_for_listings_already_live(): void
    {
        // Arrange
        $this->goLive();
        Artisan::call('outbox:relay');

        // Act
        $response = $this->post(route('saved-searches.store'), ['min_price' => '100000']);

        // Assert
        // Alerts only come from outbox messages, so none created and none pending proves no backfill.
        $response->assertRedirect();
        $this->assertSame(0, Alert::query()->count());
        $this->assertSame(0, OutboxMessage::query()->unsent()->count());
    }

    public function test_relisting_after_a_read_alert_gives_a_second_back_on_the_market_alert(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        $listing = $this->goLive();
        Artisan::call('outbox:relay');
        Alert::query()->sole()->update(['read_at' => now()]);
        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $alerts = Alert::query()->orderBy('id')->get();
        $this->assertCount(2, $alerts);
        $this->assertSame(AlertReason::NewListing, $alerts[0]->reason);
        $this->assertSame(AlertReason::BackOnMarket, $alerts[1]->reason);
        $this->assertNotSame($alerts[0]->outbox_message_id, $alerts[1]->outbox_message_id);
    }

    public function test_relisting_with_an_unread_alert_refreshes_it_instead_of_adding_one(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        $listing = $this->goLive();
        Artisan::call('outbox:relay');
        $alert = Alert::query()->sole();
        $alert->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $alert->refresh();
        $this->assertSame(1, Alert::query()->count());
        $this->assertTrue($alert->updated_at->isToday());
        $this->assertNull($alert->read_at);
        $this->assertSame(AlertReason::NewListing, $alert->reason);
    }

    public function test_a_refresh_links_newly_matching_searches_without_duplicating_links(): void
    {
        // Arrange
        $first = SavedSearch::factory()->for($this->user)->create();
        $listing = $this->goLive();
        Artisan::call('outbox:relay');
        $second = SavedSearch::factory()->for($this->user)->anyCriteria()->create();
        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $linked = Alert::query()->sole()->savedSearches->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $linked);
    }

    public function test_alerts_match_on_bathrooms_and_ignore_searches_without_them(): void
    {
        // Arrange
        $matching = SavedSearch::factory()->for($this->user)->anyCriteria()->create(['min_bathrooms' => 2]);
        $anyBaths = SavedSearch::factory()->for($this->user)->anyCriteria()->create(['min_bedrooms' => 3]);
        $tooMany = SavedSearch::factory()->for($this->user)->anyCriteria()->create(['min_bathrooms' => 3]);
        $this->goLive(['bathrooms' => 2]);

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $linked = Alert::query()->sole()->savedSearches->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$matching->id, $anyBaths->id], $linked);
        $this->assertNotContains($tooMany->id, $linked);
    }

    public function test_the_inverse_scope_agrees_with_the_listing_scope(): void
    {
        // Arrange
        $leeds = $this->branch;
        $york = Branch::factory()->create(['region' => 'York']);
        $make = fn () => SavedSearch::factory()->for($this->user)->anyCriteria();
        $make()->create(['min_price' => 100_000]);
        $make()->create(['max_price' => 150_000]);
        $make()->create(['min_price' => 150_000, 'max_price' => 150_000]);
        $make()->create(['min_bedrooms' => 3]);
        $make()->create(['max_bedrooms' => 2]);
        $make()->create(['min_bedrooms' => 2, 'max_bedrooms' => 3]);
        $make()->create(['min_bathrooms' => 2]);
        $make()->create(['max_bathrooms' => 1]);
        $make()->create(['min_bathrooms' => 1, 'max_bathrooms' => 2]);
        $make()->create();
        $make()->inRegions(['Leeds'])->create();
        $make()->inRegions(['York', 'Leeds'])->create();
        $make()->inRegions(['Hull'])->create();
        $make()->ofTypes([PropertyType::Flat])->create();
        $make()->ofTypes([PropertyType::Flat, PropertyType::Detached])->create();
        $make()->inRegions(['York'])->ofTypes([PropertyType::Detached])->create(['max_bedrooms' => 4]);
        $listings = collect([
            [$leeds, 150_000, 3, PropertyType::Flat, 2],
            [$leeds, 99_000, 1, PropertyType::Detached, 1],
            [$york, 250_000, 4, PropertyType::Detached, 3],
            [$york, 150_000, 2, PropertyType::Flat, 1],
            [$leeds, 100_000, 5, PropertyType::Detached, 2],
        ])->map(fn (array $row) => Listing::factory()->for($row[0])->live()->create([
            'price' => $row[1], 'bedrooms' => $row[2], 'property_type' => $row[3], 'bathrooms' => $row[4],
        ]));
        $searches = SavedSearch::query()->with(['regions', 'propertyTypes'])->get();

        // Act
        $disagreements = $listings->filter(function (Listing $listing) use ($searches): bool {
            $inverse = SavedSearch::query()->matchingListing($listing)->pluck('id')->sort()->values()->all();
            $forward = $searches
                ->filter(fn (SavedSearch $search) => Listing::query()
                    ->matching(SearchCriteria::fromSavedSearch($search))
                    ->whereKey($listing->id)
                    ->exists())
                ->pluck('id')->sort()->values()->all();

            return $inverse !== $forward;
        });

        // Assert
        $this->assertCount(0, $disagreements);
        $this->assertTrue($listings->contains(fn (Listing $l) => SavedSearch::query()->matchingListing($l)->exists()));
    }
}
