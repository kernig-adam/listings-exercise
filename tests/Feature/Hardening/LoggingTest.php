<?php

namespace Tests\Feature\Hardening;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Throwable;

/**
 * The log contract: one test per event, asserting level, message and the exact
 * context keys, so changing a log line is a deliberate test change. Ids and
 * counts only; never names, emails or addresses.
 */
class LoggingTest extends TestCase
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

    public function test_marking_foreign_alerts_read_logs_a_warning_with_counts_only(): void
    {
        // Arrange
        $mine = Alert::factory()->for($this->user)->create();
        $theirs = Alert::factory()->create();

        // Act
        $this->patch(route('alerts.read'), ['alert_ids' => [$mine->id, $theirs->id], 'read' => true]);

        // Assert
        $log = $this->assertLoggedOnce('warning', 'Alert ids not owned by user', ['user_id', 'requested', 'found']);
        $this->assertSame([$this->user->id, 2, 1], array_values($log->context));
        $this->assertNotLogged('alerts.marked_read');
        $this->assertNoPersonalData();
    }

    public function test_hitting_the_rate_limit_logs_a_warning_with_the_user(): void
    {
        // Arrange
        foreach (range(1, 10) as $price) {
            $this->post(route('saved-searches.store'), ['min_price' => $price]);
        }

        // Act
        $this->post(route('saved-searches.store'), ['min_price' => 11]);

        // Assert
        $log = $this->assertLoggedOnce('warning', 'Rate limit hit', ['user_id', 'route']);
        $this->assertSame([$this->user->id, 'saved-searches.store'], array_values($log->context));
        $this->assertNoPersonalData();
    }

    public function test_the_rate_limit_is_per_user_not_per_address(): void
    {
        // Arrange
        foreach (range(1, 10) as $price) {
            $this->post(route('saved-searches.store'), ['min_price' => $price]);
        }

        // Act
        $this->post(route('saved-searches.store'), ['min_price' => 11]);

        // Assert: the limiter key is the user id, so this is the stub's user, not an IP bucket.
        $this->assertSame(10, $this->user->savedSearches()->count());
        $this->assertSame($this->user->id, $this->logsNamed('Rate limit hit')[0]->context['user_id']);
    }

    public function test_a_listing_going_live_logs_its_outbox_message(): void
    {
        // Act
        $listing = Listing::factory()->live()->create();

        // Assert
        $log = $this->assertLoggedOnce('info', 'Listing went live', ['listing_id', 'outbox_message_id']);
        $this->assertSame([$listing->id, OutboxMessage::query()->sole()->id], array_values($log->context));
        $this->assertNoPersonalData();
    }

    public function test_generating_alerts_logs_the_counts(): void
    {
        // Arrange
        SavedSearch::factory()->anyCriteria()->for($this->user)->create();
        $listing = Listing::factory()->live()->create();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $log = $this->assertLoggedOnce('info', 'Alerts generated', [
            'outbox_message_id', 'listing_id', 'matched_searches', 'users', 'created', 'refreshed',
        ]);
        $this->assertSame([OutboxMessage::query()->sole()->id, $listing->id, 1, 1, 1, 0], array_values($log->context));
        $this->assertNoPersonalData();
    }

    public function test_a_refreshed_alert_is_counted_as_refreshed(): void
    {
        // Arrange
        SavedSearch::factory()->anyCriteria()->for($this->user)->create();
        $listing = Listing::factory()->live()->create();
        Artisan::call('outbox:relay');
        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $context = $this->logsNamed('Alerts generated')[0]->context;
        $this->assertSame(0, $context['created']);
        $this->assertSame(1, $context['refreshed']);
    }

    public function test_skipped_go_lives_log_the_reason(): void
    {
        // Arrange
        $notLive = Listing::factory()->live()->create();
        $notLive->update(['status' => ListingStatus::Sold]);
        Listing::factory()->live()->create();
        OutboxMessage::factory()->create(['payload' => ['listing_id' => 999999, 'occurred_at' => now()->toIso8601String()]]);
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $skipped = $this->logsNamed('Listing went live, no alerts');
        $reasons = array_map(fn (MessageLogged $log) => $log->context['reason'], $skipped);
        sort($reasons);
        $this->assertSame(['no_matches', 'not_found', 'not_live'], $reasons);
        $this->assertSame('info', $skipped[0]->level);
        $this->assertSame(['outbox_message_id', 'listing_id', 'reason'], array_keys($skipped[0]->context));
        $this->assertNoPersonalData();
    }

    public function test_a_relay_run_with_a_failure_logs_a_warning_summary(): void
    {
        // Arrange
        OutboxMessage::factory()->create();
        OutboxMessage::factory()->create(['type' => 'unknown.type']);

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $log = $this->assertLoggedOnce('warning', 'Outbox relay run', ['processed', 'failed', 'duration_ms']);
        $this->assertSame(1, $log->context['processed']);
        $this->assertSame(1, $log->context['failed']);
    }

    public function test_a_clean_relay_run_logs_at_info(): void
    {
        // Arrange
        OutboxMessage::factory()->create();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertLoggedOnce('info', 'Outbox relay run', ['processed', 'failed', 'duration_ms']);
    }

    public function test_an_idle_relay_run_logs_nothing(): void
    {
        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame([], $this->logged);
    }

    public function test_a_relay_failure_logs_the_exception(): void
    {
        // Arrange
        $bad = OutboxMessage::factory()->create(['type' => 'unknown.type']);

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $log = $this->assertLoggedOnce('error', 'Outbox relay failed', ['outbox_message_id', 'type', 'exception']);
        $this->assertSame($bad->id, $log->context['outbox_message_id']);
        $this->assertSame('unknown.type', $log->context['type']);
        $this->assertInstanceOf(Throwable::class, $log->context['exception']);
    }
}
