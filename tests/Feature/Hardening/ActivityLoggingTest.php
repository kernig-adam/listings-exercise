<?php

namespace Tests\Feature\Hardening;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Buyer activity goes to its own `activity` channel so it can be routed apart
 * from application and error logs. Ids, counts and search terms only.
 */
class ActivityLoggingTest extends TestCase
{
    use CapturesLogs;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Zed Zebra', 'email' => 'zed@zebra.test']);
        $this->captureLogs();
    }

    /**
     * @return list<LogRecord>
     */
    private function activity(string $message): array
    {
        $handler = Log::channel('activity')->getLogger()->getHandlers()[0];
        $this->assertInstanceOf(TestHandler::class, $handler);

        return array_values(array_filter($handler->getRecords(), fn (LogRecord $r) => $r->message === $message));
    }

    /**
     * @param  list<string>  $keys
     */
    private function assertActivityOnce(string $message, array $keys): LogRecord
    {
        $records = $this->activity($message);
        $this->assertCount(1, $records, "Expected exactly one [{$message}] activity event");
        $this->assertSame('INFO', $records[0]->level->getName());
        $this->assertSame($keys, array_keys($records[0]->context));
        $this->assertNoPersonalData();

        return $records[0];
    }

    public function test_browsing_listings_logs_the_search_with_normalised_filters(): void
    {
        // Arrange
        Branch::factory()->create(['region' => 'Leeds']);
        Listing::factory()->live()->count(2)->create(['price' => 150_000]);

        // Act
        $this->get(route('listings.index', ['min_price' => 100000, 'region' => 'leeds']));

        // Assert
        $record = $this->assertActivityOnce('search.performed', ['user_id', 'filters', 'result_count', 'page']);
        $this->assertSame($this->user->id, $record->context['user_id']);
        $this->assertSame(['Leeds'], $record->context['filters']['region']);
        $this->assertSame(100000, $record->context['filters']['min_price']);
        $this->assertSame(1, $record->context['page']);
    }

    public function test_an_unfiltered_view_is_logged_too_as_the_baseline(): void
    {
        // Arrange
        Listing::factory()->live()->count(3)->create();

        // Act
        $this->get(route('listings.index'));

        // Assert
        $record = $this->assertActivityOnce('search.performed', ['user_id', 'filters', 'result_count', 'page']);
        $this->assertSame(3, $record->context['result_count']);
    }

    public function test_an_inertia_partial_reload_is_not_logged_again(): void
    {
        // Act
        $this->get(route('listings.index'), ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Listings/Index', 'X-Inertia-Partial-Data' => 'listings']);

        // Assert
        $this->assertSame([], $this->activity('search.performed'));
    }

    public function test_a_rejected_search_is_not_logged_as_performed(): void
    {
        // Act
        $this->get(route('listings.index', ['min_price' => 'abc']));

        // Assert
        $this->assertSame([], $this->activity('search.performed'));
    }

    public function test_viewing_a_listing_logs_browse_by_default(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();

        // Act
        $this->get(route('listings.show', $listing));

        // Assert
        $record = $this->assertActivityOnce('listing.viewed', ['user_id', 'listing_id', 'source']);
        $this->assertSame([$this->user->id, $listing->id, 'browse'], array_values($record->context));
    }

    public function test_arriving_from_an_alert_card_is_logged_as_alert(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();

        // Act
        $this->get(route('listings.show', ['listing' => $listing, 'from' => 'alert']));

        // Assert
        $record = $this->assertActivityOnce('listing.viewed', ['user_id', 'listing_id', 'source']);
        $this->assertSame('alert', $record->context['source']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function junkSources(): array
    {
        return [
            'other word' => ['email'],
            'upper case' => ['ALERT'],
            'script' => ['<script>alert(1)</script>'],
            'array' => [['alert']],
            'long' => [str_repeat('a', 10_000)],
            'injection' => ["alert'; DROP TABLE alerts; --"],
        ];
    }

    #[DataProvider('junkSources')]
    public function test_a_junk_source_is_treated_as_browse_and_never_logged_raw(mixed $from): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();

        // Act
        $response = $this->get(route('listings.show', ['listing' => $listing, 'from' => $from]));

        // Assert
        $response->assertOk();
        $record = $this->assertActivityOnce('listing.viewed', ['user_id', 'listing_id', 'source']);
        $this->assertSame('browse', $record->context['source']);
    }

    public function test_a_listing_that_is_not_live_is_not_logged_as_viewed(): void
    {
        // Arrange
        $listing = Listing::factory()->sold()->create();

        // Act
        $response = $this->get(route('listings.show', $listing));

        // Assert
        $response->assertNotFound();
        $this->assertSame([], $this->activity('listing.viewed'));
    }

    public function test_viewing_saved_searches_logs_the_count(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->count(2)->create();
        SavedSearch::factory()->create();

        // Act
        $this->get(route('saved-searches.index'));

        // Assert
        $record = $this->assertActivityOnce('saved_searches.viewed', ['user_id', 'count']);
        $this->assertSame([$this->user->id, 2], array_values($record->context));
    }

    public function test_viewing_alerts_logs_homes_shown_and_new_count(): void
    {
        // Arrange
        Alert::factory()->for($this->user)->create();
        Alert::factory()->for($this->user)->read()->create();
        Alert::factory()->create();

        // Act
        $this->get(route('alerts.index'));

        // Assert
        $record = $this->assertActivityOnce('alerts.viewed', ['user_id', 'homes_shown', 'new_count']);
        $this->assertSame([$this->user->id, 2, 1], array_values($record->context));
    }

    public function test_saving_a_search_is_logged_as_activity(): void
    {
        // Act
        $this->post(route('saved-searches.store'), ['min_price' => 5]);

        // Assert
        $record = $this->assertActivityOnce('saved_search.created', ['user_id', 'saved_search_id']);
        $this->assertSame([$this->user->id, $this->user->savedSearches()->sole()->id], array_values($record->context));
    }

    public function test_deleting_a_search_is_logged_as_activity(): void
    {
        // Arrange
        $search = SavedSearch::factory()->for($this->user)->create();

        // Act
        $this->delete(route('saved-searches.destroy', $search->id));

        // Assert
        $record = $this->assertActivityOnce('saved_search.deleted', ['user_id', 'saved_search_id']);
        $this->assertSame([$this->user->id, $search->id], array_values($record->context));
    }

    public function test_marking_alerts_read_is_logged_as_activity(): void
    {
        // Arrange
        $alert = Alert::factory()->for($this->user)->create();

        // Act
        $this->patch(route('alerts.read'), ['alert_ids' => [$alert->id], 'read' => true]);

        // Assert
        $record = $this->assertActivityOnce('alerts.marked_read', ['user_id', 'homes']);
        $this->assertSame([$this->user->id, 1], array_values($record->context));
    }
}
