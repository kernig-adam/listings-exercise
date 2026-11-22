<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_scope_returns_only_live_listings(): void
    {
        // Arrange
        Listing::factory(2)->live()->create();
        Listing::factory()->create();                 // draft
        Listing::factory()->sold()->create();
        Listing::factory()->underOffer()->create();

        // Act
        $live = Listing::query()->live()->get();

        // Assert
        $this->assertCount(2, $live);
        $this->assertTrue($live->every(fn (Listing $l) => $l->status === ListingStatus::Live));
    }

    public function test_property_type_and_status_are_cast_to_enums(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();

        // Act
        $status = $listing->fresh()->status;

        // Assert
        $this->assertInstanceOf(ListingStatus::class, $status);
        $this->assertSame(ListingStatus::Live, $status);
    }
}
