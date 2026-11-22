<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_live_listing_writes_one_outbox_message(): void
    {
        // Act
        $listing = Listing::factory()->live()->create();

        // Assert
        $message = OutboxMessage::query()->sole();
        $this->assertSame('listing.went_live', $message->type);
        $this->assertSame($listing->id, $message->payload['listing_id']);
        $this->assertNotFalse(strtotime($message->payload['occurred_at']));
        $this->assertNull($message->sent_at);
    }

    public function test_publishing_a_draft_writes_one_outbox_message(): void
    {
        // Arrange
        $listing = Listing::factory()->draft()->create();

        // Act
        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        // Assert
        $this->assertSame(1, OutboxMessage::query()->count());
    }

    public function test_coming_back_from_under_offer_writes_one_outbox_message(): void
    {
        // Arrange
        $listing = Listing::factory()->underOffer()->create();

        // Act
        $listing->update(['status' => ListingStatus::Live]);

        // Assert
        $this->assertSame(1, OutboxMessage::query()->count());
    }

    public function test_coming_back_from_sold_writes_one_outbox_message(): void
    {
        // Arrange
        $listing = Listing::factory()->sold()->create();

        // Act
        $listing->update(['status' => ListingStatus::Live]);

        // Assert
        $this->assertSame(1, OutboxMessage::query()->count());
    }

    public function test_editing_an_already_live_listing_writes_nothing(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();
        OutboxMessage::query()->delete();

        // Act
        $listing->update(['price' => $listing->price + 1000, 'status' => ListingStatus::Live]);

        // Assert
        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_saving_an_unchanged_live_listing_again_writes_nothing(): void
    {
        // Arrange
        $listing = Listing::factory()->draft()->create();
        $listing->update(['status' => ListingStatus::Live]);
        OutboxMessage::query()->delete();

        // Act
        $listing->save();

        // Assert
        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_moves_to_a_non_live_status_write_nothing(): void
    {
        // Arrange
        $listing = Listing::factory()->draft()->create();

        // Act
        $listing->update(['status' => ListingStatus::UnderOffer]);

        // Assert
        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_a_failed_outbox_write_rolls_back_the_status_change(): void
    {
        // Arrange
        $listing = Listing::factory()->draft()->create();
        OutboxMessage::creating(fn () => throw new RuntimeException('outbox down'));
        $thrown = null;

        // Act
        try {
            $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        // Assert
        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }

    public function test_a_failed_outbox_write_leaves_no_message(): void
    {
        // Arrange
        $listing = Listing::factory()->draft()->create();
        OutboxMessage::creating(fn () => throw new RuntimeException('outbox down'));
        $thrown = null;

        // Act
        try {
            $listing->update(['status' => ListingStatus::Live]);
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        // Assert
        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_the_relay_marks_messages_sent(): void
    {
        // Arrange
        Listing::factory()->live()->create();

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertNotNull(OutboxMessage::query()->sole()->sent_at);
    }

    public function test_the_relay_does_not_resend_sent_messages(): void
    {
        // Arrange
        $sentAt = now()->subHour();
        $message = OutboxMessage::factory()->create(['type' => 'unknown.type', 'sent_at' => $sentAt]);

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame($sentAt->timestamp, $message->fresh()->sent_at->timestamp);
    }

    public function test_a_failing_message_stays_unsent_and_is_logged(): void
    {
        // Arrange
        Log::spy();
        $bad = OutboxMessage::factory()->create(['type' => 'unknown.type']);

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertNull($bad->fresh()->sent_at);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_failing_message_does_not_block_the_ones_after_it(): void
    {
        // Arrange
        OutboxMessage::factory()->create(['type' => 'unknown.type']);
        $good = OutboxMessage::factory()->create();

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertNotNull($good->fresh()->sent_at);
    }

    public function test_a_failed_message_is_retried_on_the_next_run(): void
    {
        // Arrange
        Log::spy();
        OutboxMessage::factory()->create(['type' => 'unknown.type']);
        Artisan::call('outbox:relay');

        // Act
        $exitCode = Artisan::call('outbox:relay');

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        Log::shouldHaveReceived('error')->twice();
    }

    public function test_the_relay_is_scheduled(): void
    {
        // Act
        $exitCode = Artisan::call('schedule:list');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('outbox:relay', Artisan::output());
    }
}
