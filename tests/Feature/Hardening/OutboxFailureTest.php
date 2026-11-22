<?php

namespace Tests\Feature\Hardening;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\SavedSearch;
use App\Models\User;
use App\Outbox\ListingWentLive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Failure injection, idempotency and ordering on the outbox path. True
 * concurrency (two relays racing) cannot be reproduced on single-connection
 * SQLite, so it is not faked here; the consumer's per-message dedup is
 * exercised by reprocessing instead.
 */
class OutboxFailureTest extends TestCase
{
    use CapturesLogs;
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['name' => 'Zed Zebra']);
    }

    private function searchFor(User $user): SavedSearch
    {
        return SavedSearch::factory()->anyCriteria()->for($user)->create();
    }

    private function alertsGenerated(): int
    {
        return count($this->logsNamed('Alerts generated'));
    }

    // --- failure injection -------------------------------------------------

    public function test_a_consumer_failing_mid_loop_rolls_everything_back_and_the_retry_creates_each_alert_once(): void
    {
        // Arrange
        foreach (range(1, 3) as $ignored) {
            $this->searchFor($this->user());
        }
        Listing::factory()->live()->create();
        $failing = true;
        $calls = 0;
        Alert::creating(function () use (&$failing, &$calls): void {
            if ($failing && ++$calls === 2) {
                throw new RuntimeException('boom on the second user');
            }
        });
        $this->captureLogs();
        Artisan::call('outbox:relay');
        $failedRunLogs = $this->logged;
        $failing = false;
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert: the failed run left nothing behind and said why...
        $failedErrors = array_values(array_filter($failedRunLogs, fn ($log) => $log->message === 'Outbox relay failed'));
        $this->assertCount(1, $failedErrors);
        $this->assertSame('error', $failedErrors[0]->level);
        $this->assertSame(['outbox_message_id', 'type', 'exception'], array_keys($failedErrors[0]->context));
        $this->assertInstanceOf(RuntimeException::class, $failedErrors[0]->context['exception']);
        $this->assertSame([], array_filter($failedRunLogs, fn ($log) => $log->message === 'Alerts generated'));
        // ...and the retry made each alert exactly once.
        $this->assertSame(3, Alert::query()->count());
        $this->assertSame(3, Alert::query()->distinct()->count('user_id'));
        $this->assertSame(3, DB::table('alert_saved_search')->count());
        $this->assertNotNull(OutboxMessage::query()->sole()->sent_at);
        $this->assertSame(1, $this->alertsGenerated());
        $this->assertNoPersonalData();
    }

    public function test_a_failed_run_leaves_no_partial_alerts_and_the_message_unsent(): void
    {
        // Arrange
        foreach (range(1, 3) as $ignored) {
            $this->searchFor($this->user());
        }
        Listing::factory()->live()->create();
        $calls = 0;
        Alert::creating(function () use (&$calls): void {
            if (++$calls === 2) {
                throw new RuntimeException('boom');
            }
        });
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(0, Alert::query()->count());
        $this->assertSame(0, DB::table('alert_saved_search')->count());
        $this->assertNull(OutboxMessage::query()->sole()->sent_at);
        $summary = $this->assertLoggedOnce('warning', 'Outbox relay run', ['processed', 'failed', 'duration_ms']);
        $this->assertSame([0, 1], [$summary->context['processed'], $summary->context['failed']]);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function badMiddleMessages(): array
    {
        return [
            'unknown type' => [['type' => 'unknown.type']],
            'malformed payload' => [['payload' => ['listing_id' => 'x', 'occurred_at' => 'now-ish']]],
            'payload missing' => [['payload' => []]],
        ];
    }

    /**
     * @param  array<string, mixed>  $bad
     */
    #[DataProvider('badMiddleMessages')]
    public function test_a_bad_message_in_the_middle_does_not_stop_its_neighbours(array $bad): void
    {
        // Arrange
        $this->searchFor($this->user());
        $first = Listing::factory()->live()->create();
        $middle = OutboxMessage::factory()->create($bad);
        $third = Listing::factory()->live()->create();
        $this->captureLogs();

        // Act
        $exit = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(1, $exit);
        $this->assertNull($middle->fresh()->sent_at);
        $this->assertSame(1, OutboxMessage::query()->unsent()->count());
        $this->assertSame([$first->id, $third->id], Alert::query()->orderBy('id')->pluck('listing_id')->all());
        $this->assertLoggedOnce('error', 'Outbox relay failed', ['outbox_message_id', 'type', 'exception']);
        $summary = $this->assertLoggedOnce('warning', 'Outbox relay run', ['processed', 'failed', 'duration_ms']);
        $this->assertSame([2, 1], [$summary->context['processed'], $summary->context['failed']]);
        $this->assertNoPersonalData();
    }

    public function test_a_message_whose_listing_was_deleted_is_skipped_and_marked_sent(): void
    {
        // Arrange
        $this->searchFor($this->user());
        $gone = Listing::factory()->live()->create();
        $kept = Listing::factory()->live()->create();
        $gone->delete();
        $this->captureLogs();

        // Act
        $exit = Artisan::call('outbox:relay');

        // Assert: nothing to alert on is not a failure, so it is not retried forever.
        $this->assertSame(0, $exit);
        $this->assertSame(0, OutboxMessage::query()->unsent()->count());
        $this->assertSame([$kept->id], Alert::query()->pluck('listing_id')->all());
        $skip = $this->assertLoggedOnce('info', 'Listing went live, no alerts', ['outbox_message_id', 'listing_id', 'reason']);
        $this->assertSame([$gone->id, 'not_found'], [$skip->context['listing_id'], $skip->context['reason']]);
    }

    public function test_a_listing_set_back_to_draft_before_the_relay_gets_no_alert(): void
    {
        // Arrange
        $this->searchFor($this->user());
        $listing = Listing::factory()->live()->create();
        $listing->update(['status' => ListingStatus::Draft]);
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(0, Alert::query()->count());
        $skip = $this->assertLoggedOnce('info', 'Listing went live, no alerts', ['outbox_message_id', 'listing_id', 'reason']);
        $this->assertSame('not_live', $skip->context['reason']);
        $this->assertSame(0, $this->alertsGenerated());
    }

    public function test_a_search_deleted_before_the_relay_gets_no_alert_but_others_still_do(): void
    {
        // Arrange
        $deleted = $this->searchFor($this->user());
        $kept = $this->searchFor($keeper = $this->user());
        Listing::factory()->live()->create();
        $deleted->delete();
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame([$keeper->id], Alert::query()->pluck('user_id')->all());
        $this->assertSame([$kept->id], DB::table('alert_saved_search')->pluck('saved_search_id')->all());
        $log = $this->assertLoggedOnce('info', 'Alerts generated', [
            'outbox_message_id', 'listing_id', 'matched_searches', 'users', 'created', 'refreshed',
        ]);
        $this->assertSame([1, 1, 1, 0], [$log->context['matched_searches'], $log->context['users'], $log->context['created'], $log->context['refreshed']]);
    }

    public function test_a_failing_outbox_insert_rolls_the_new_listing_back_and_logs_nothing(): void
    {
        // Arrange
        $this->captureLogs();
        OutboxMessage::creating(fn () => throw new RuntimeException('outbox down'));

        // Act
        $caught = null;
        try {
            Listing::factory()->live()->create();
        } catch (Throwable $e) {
            $caught = $e;
        }

        // Assert
        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertSame(0, Listing::query()->count());
        $this->assertNotLogged('Listing went live');
    }

    public function test_a_failing_outbox_insert_leaves_an_existing_listing_in_its_old_status(): void
    {
        // Arrange
        $listing = Listing::factory()->draft()->create();
        OutboxMessage::creating(fn () => throw new RuntimeException('outbox down'));
        $this->captureLogs();

        // Act
        try {
            $listing->update(['status' => ListingStatus::Live]);
        } catch (RuntimeException) {
            // expected: the point is what the database holds afterwards
        }

        // Assert
        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
        $this->assertNotLogged('Listing went live');
    }

    // --- idempotency and ordering -----------------------------------------

    public function test_the_same_message_processed_three_times_makes_one_alert(): void
    {
        // Arrange
        $this->searchFor($this->user());
        Listing::factory()->live()->create();
        $message = OutboxMessage::query()->sole();
        $this->captureLogs();

        // Act
        foreach (range(1, 3) as $ignored) {
            app(ListingWentLive::class)->handle($message);
        }

        // Assert
        $this->assertSame(1, Alert::query()->count());
        $created = array_map(fn ($log) => $log->context['created'], $this->logsNamed('Alerts generated'));
        $refreshed = array_map(fn ($log) => $log->context['refreshed'], $this->logsNamed('Alerts generated'));
        $this->assertSame([1, 0, 0], $created);
        $this->assertSame([0, 0, 0], $refreshed);
    }

    public function test_two_go_live_messages_for_one_listing_in_one_run_make_one_alert_then_refresh_it(): void
    {
        // Arrange
        $this->searchFor($this->user());
        $listing = Listing::factory()->live()->create();
        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(2, OutboxMessage::query()->count());
        $this->assertSame(1, Alert::query()->count());
        $generated = $this->logsNamed('Alerts generated');
        $this->assertSame([1, 0], array_map(fn ($log) => $log->context['created'], $generated));
        $this->assertSame([0, 1], array_map(fn ($log) => $log->context['refreshed'], $generated));
        $summary = $this->assertLoggedOnce('info', 'Outbox relay run', ['processed', 'failed', 'duration_ms']);
        $this->assertSame(2, $summary->context['processed']);
    }

    public function test_a_sent_message_is_skipped_by_the_relay_and_logs_nothing(): void
    {
        // Arrange
        $this->searchFor($this->user());
        Listing::factory()->live()->create();
        Artisan::call('outbox:relay');
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(1, Alert::query()->count());
        $this->assertSame([], $this->logged);
    }

    public function test_a_flip_flop_after_the_alert_was_read_makes_a_new_back_on_market_alert(): void
    {
        // Arrange
        $user = $this->user();
        $this->searchFor($user);
        $listing = Listing::factory()->live()->create();
        Artisan::call('outbox:relay');
        Alert::query()->update(['read_at' => now()]);
        $listing->update(['status' => ListingStatus::Sold]);
        $listing->update(['status' => ListingStatus::Live]);
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(2, Alert::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, $this->logsNamed('Alerts generated')[0]->context['created']);
    }

    public function test_listings_in_other_regions_do_not_alert_a_region_search(): void
    {
        // Arrange
        $user = $this->user();
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        $hull = Branch::factory()->create(['region' => 'Hull']);
        $search = SavedSearch::factory()->anyCriteria()->for($user)->create();
        $search->regions()->create(['region' => 'Leeds']);
        Listing::factory()->for($hull)->live()->create();
        $this->captureLogs();

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(0, Alert::query()->count());
        $this->assertSame('no_matches', $this->logsNamed('Listing went live, no alerts')[0]->context['reason']);
        $this->assertNotNull($leeds);
    }
}
