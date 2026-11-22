<?php

namespace Database\Factories;

use App\Enums\AlertReason;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'listing_id' => Listing::factory()->live(),
            'outbox_message_id' => OutboxMessage::factory(),
            'reason' => AlertReason::NewListing,
            'read_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn () => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }

    public function backOnMarket(): static
    {
        return $this->state(fn () => ['reason' => AlertReason::BackOnMarket]);
    }
}
