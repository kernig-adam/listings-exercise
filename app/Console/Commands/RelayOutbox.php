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

    public function handle(): int
    {
        $failed = 0;

        // id order keeps events in the order they happened.
        foreach (OutboxMessage::query()->unsent()->orderBy('id')->get() as $message) {
            try {
                $handler = self::HANDLERS[$message->type] ?? throw new RuntimeException("No handler for [{$message->type}]");

                // In production this publishes to SNS; handing over in-process stands in for it.
                app($handler)->handle($message);

                $message->update(['sent_at' => now()]);
            } catch (Throwable $e) {
                // Left unsent so the next run retries; one bad message must not block the rest.
                $failed++;
                Log::error('Outbox relay failed', [
                    'outbox_message_id' => $message->id,
                    'type' => $message->type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
