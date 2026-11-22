<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\SavedSearch;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WentLivePayloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformedPayloads(): array
    {
        $at = '2026-01-01T10:00:00+00:00';

        return [
            'missing listing_id' => [['occurred_at' => $at]],
            'zero listing_id' => [['listing_id' => 0, 'occurred_at' => $at]],
            'negative listing_id' => [['listing_id' => -3, 'occurred_at' => $at]],
            'string listing_id' => [['listing_id' => 'abc', 'occurred_at' => $at]],
            'missing occurred_at' => [['listing_id' => 1]],
            'bad occurred_at' => [['listing_id' => 1, 'occurred_at' => 'yesterday-ish']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('malformedPayloads')]
    public function test_a_malformed_payload_stays_unsent_is_logged_and_does_not_block_the_next(array $payload): void
    {
        // Arrange
        Log::spy();
        $bad = OutboxMessage::factory()->create(['payload' => $payload]);
        $next = OutboxMessage::factory()->create();

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertNull($bad->fresh()->sent_at);
        $this->assertNotNull($next->fresh()->sent_at);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_malformed_payload_creates_no_alerts(): void
    {
        // Arrange
        SavedSearch::factory()->create();
        OutboxMessage::factory()->create(['payload' => ['listing_id' => 0, 'occurred_at' => 'nope']]);

        // Act
        Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(0, Alert::query()->count());
    }

    public function test_going_live_without_a_listed_date_defaults_it_to_now(): void
    {
        // Act
        $listing = Listing::factory()->live()->create(['listed_at' => null]);

        // Assert
        $this->assertNotNull($listing->fresh()->listed_at);
    }

    public function test_going_live_keeps_an_existing_listed_date(): void
    {
        // Arrange
        $listedAt = now()->subWeek()->startOfSecond();

        // Act
        $listing = Listing::factory()->live()->create(['listed_at' => $listedAt]);

        // Assert
        $this->assertSame($listedAt->timestamp, $listing->fresh()->listed_at->timestamp);
    }
}
