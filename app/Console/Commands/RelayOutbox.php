<?php

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use App\Outbox\ListingWentLive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay';

    protected $description = 'Hand unsent outbox messages to their consumers and mark them sent';

    /** Message type to consumer; one entry until a second event exists. */
    private const HANDLERS = [
        OutboxMessage::LISTING_WENT_LIVE => ListingWentLive::class,
    ];

    /**
     * Publishes every unsent outbox message to its consumer, oldest first, and marks it sent.
     * A failed message stays unsent and is retried on the next run; the others still go. Returns FAILURE if any failed.
     */
    public function handle(): int
    {
        $started = hrtime(true);
        $processed = 0;
        $failed = 0;

        // id order keeps events in the order they happened.
        foreach (OutboxMessage::query()->unsent()->orderBy('id')->get() as $message) {
            try {
                $handler = self::HANDLERS[$message->type] ?? throw new RuntimeException("No handler for [{$message->type}]");

                // In production this publishes to SNS; handing over in-process stands in for it.
                app($handler)->handle($message);

                $message->update(['sent_at' => now()]);
                $processed++;
            } catch (Throwable $e) {
                // Left unsent so the next run retries; one bad message must not block the rest.
                $failed++;
                Log::error('Outbox relay failed', [
                    'outbox_message_id' => $message->id,
                    'type' => $message->type,
                    'exception' => $e,
                ]);
            }
        }

        // Silent when idle: the scheduler runs every minute and an empty run says nothing.
        if ($processed + $failed > 0) {
            Log::log($failed > 0 ? 'warning' : 'info', 'Outbox relay run', [
                'processed' => $processed,
                'failed' => $failed,
                'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            ]);
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
