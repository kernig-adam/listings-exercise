<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The demo user that the auth stub resolves every API request as.
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@street.example',
        ]);

        $branches = Branch::factory(8)->create();

        foreach ($branches as $branch) {
            Listing::factory(rand(18, 30))->live()->for($branch)->create();
            Listing::factory(rand(3, 6))->underOffer()->for($branch)->create();
            Listing::factory(rand(4, 8))->sold()->for($branch)->create();
            Listing::factory(rand(2, 5))->for($branch)->create(); // drafts
        }

        // The stock above is not "new": mark its go-live messages sent so the first
        // relay run does not flood the demo user with alerts for it.
        OutboxMessage::query()->update(['sent_at' => now()]);

        // A broad demo search, so a listing set live while demoing produces an alert.
        $demo->savedSearches()->create(['max_price' => 400_000, 'min_bedrooms' => 2]);
    }
}
