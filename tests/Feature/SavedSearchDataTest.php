<?php

namespace Tests\Feature;

use App\Enums\AlertReason;
use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_alert_per_user_per_outbox_message(): void
    {
        $alert = Alert::factory()->create();

        $this->expectException(QueryException::class);

        Alert::factory()->create([
            'user_id' => $alert->user_id,
            'outbox_message_id' => $alert->outbox_message_id,
        ]);
    }

    public function test_same_user_can_be_alerted_again_for_a_new_outbox_message(): void
    {
        $first = Alert::factory()->read()->create();

        $second = Alert::factory()->backOnMarket()->create([
            'user_id' => $first->user_id,
            'listing_id' => $first->listing_id,
        ]);

        $this->assertSame(2, Alert::query()->where('user_id', $first->user_id)->count());
        $this->assertSame(AlertReason::BackOnMarket, $second->reason);
    }

    public function test_two_users_can_share_one_outbox_message(): void
    {
        $alert = Alert::factory()->create();

        Alert::factory()->create([
            'listing_id' => $alert->listing_id,
            'outbox_message_id' => $alert->outbox_message_id,
        ]);

        $this->assertSame(2, Alert::query()->count());
    }

    public function test_a_search_cannot_list_the_same_region_twice(): void
    {
        $search = SavedSearch::factory()->inRegions(['Leeds'])->create();

        $this->expectException(QueryException::class);

        $search->regions()->create(['region' => 'Leeds']);
    }

    public function test_a_search_cannot_list_the_same_property_type_twice(): void
    {
        $search = SavedSearch::factory()->ofTypes([PropertyType::Flat])->create();

        $this->expectException(QueryException::class);

        $search->propertyTypes()->create(['property_type' => PropertyType::Flat]);
    }

    public function test_an_alert_cannot_link_the_same_search_twice(): void
    {
        $alert = Alert::factory()->create();
        $search = SavedSearch::factory()->create();
        $alert->savedSearches()->attach($search);

        $this->expectException(QueryException::class);

        $alert->savedSearches()->attach($search);
    }

    public function test_deleting_a_saved_search_keeps_its_alerts_and_removes_the_link(): void
    {
        $alert = Alert::factory()->create();
        $kept = SavedSearch::factory()->create();
        $deleted = SavedSearch::factory()->inRegions(['Leeds'])->ofTypes([PropertyType::Flat])->create();
        $alert->savedSearches()->attach([$kept->id, $deleted->id]);

        $deleted->delete();

        $this->assertModelExists($alert);
        $this->assertSame([$kept->id], $alert->savedSearches()->pluck('saved_searches.id')->all());
        $this->assertDatabaseCount('saved_search_regions', 0);
        $this->assertDatabaseCount('saved_search_property_types', 0);
    }

    public function test_deleting_a_user_removes_their_searches_and_alerts(): void
    {
        $alert = Alert::factory()->create();
        $search = SavedSearch::factory()->create(['user_id' => $alert->user_id]);
        $alert->savedSearches()->attach($search);

        $alert->user->delete();

        $this->assertModelMissing($alert);
        $this->assertModelMissing($search);
        $this->assertDatabaseCount('alert_saved_search', 0);
    }

    public function test_deleting_a_listing_removes_its_alerts_but_not_the_user(): void
    {
        $alert = Alert::factory()->create();

        $alert->listing->delete();

        $this->assertModelMissing($alert);
        $this->assertModelExists($alert->user);
    }

    public function test_an_outbox_message_with_alerts_cannot_be_deleted(): void
    {
        $alert = Alert::factory()->create();

        $this->expectException(QueryException::class);

        $alert->outboxMessage->delete();
    }

    public function test_enums_dates_and_payload_are_cast(): void
    {
        $search = SavedSearch::factory()->ofTypes([PropertyType::Detached])->create();
        $alert = Alert::factory()->backOnMarket()->read()->create()->fresh();
        $message = OutboxMessage::factory()->sent()->create(['payload' => ['listing_id' => 7]])->fresh();

        $this->assertSame(PropertyType::Detached, $search->propertyTypes()->first()->property_type);
        $this->assertSame(AlertReason::BackOnMarket, $alert->reason);
        $this->assertNotNull($alert->read_at);
        $this->assertNotNull($message->sent_at);
        $this->assertSame(['listing_id' => 7], $message->payload);
        $this->assertIsInt($search->fresh()->min_price);
    }

    public function test_alert_reason_labels(): void
    {
        $this->assertSame('New listing', AlertReason::NewListing->label());
        $this->assertSame('Back on the market', AlertReason::BackOnMarket->label());
    }

    public function test_relationships_resolve(): void
    {
        $alert = Alert::factory()->create();
        $search = SavedSearch::factory()
            ->inRegions(['Leeds', 'Bristol'])
            ->ofTypes([PropertyType::Flat])
            ->create(['user_id' => $alert->user_id]);
        $alert->savedSearches()->attach($search);

        $this->assertInstanceOf(User::class, $alert->user);
        $this->assertInstanceOf(Listing::class, $alert->listing);
        $this->assertInstanceOf(OutboxMessage::class, $alert->outboxMessage);
        $this->assertTrue($alert->user->alerts->contains($alert));
        $this->assertTrue($alert->user->savedSearches->contains($search));
        $this->assertTrue($search->user->is($alert->user));
        $this->assertEqualsCanonicalizing(['Leeds', 'Bristol'], $search->regions->pluck('region')->all());
        $this->assertTrue($search->regions->first()->savedSearch->is($search));
        $this->assertTrue($search->propertyTypes->first()->savedSearch->is($search));
        $this->assertTrue($search->alerts->contains($alert));
    }

    public function test_unread_scope_returns_only_unread_alerts(): void
    {
        $unread = Alert::factory()->unread()->create();
        Alert::factory()->read()->create();

        $this->assertSame([$unread->id], Alert::query()->unread()->pluck('id')->all());
    }

    public function test_unsent_scope_returns_only_unsent_messages(): void
    {
        $unsent = OutboxMessage::factory()->unsent()->create();
        OutboxMessage::factory()->sent()->create();

        $this->assertSame([$unsent->id], OutboxMessage::query()->unsent()->pluck('id')->all());
    }

    public function test_user_id_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $search = new SavedSearch(['user_id' => $user->id, 'min_price' => 1]);
        $alert = new Alert(['user_id' => $user->id, 'reason' => AlertReason::NewListing]);

        $this->assertNull($search->user_id);
        $this->assertNull($alert->user_id);
    }

    public function test_any_criteria_search_has_no_bounds(): void
    {
        $search = SavedSearch::factory()->anyCriteria()->create()->fresh();

        $this->assertNull($search->min_price);
        $this->assertNull($search->max_price);
        $this->assertNull($search->min_bedrooms);
        $this->assertNull($search->max_bedrooms);
        $this->assertCount(0, $search->regions);
        $this->assertCount(0, $search->propertyTypes);
    }
}
