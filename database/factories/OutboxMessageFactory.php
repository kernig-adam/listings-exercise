<?php

namespace Database\Factories;

use App\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboxMessage>
 */
class OutboxMessageFactory extends Factory
{
    protected $model = OutboxMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'listing.went_live',
            'payload' => ['listing_id' => fake()->numberBetween(1, 1000), 'occurred_at' => now()->toIso8601String()],
            'sent_at' => null,
        ];
    }

    public function unsent(): static
    {
        return $this->state(fn () => ['sent_at' => null]);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['sent_at' => now()]);
    }
}
