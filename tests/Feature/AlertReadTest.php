<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlertReadTest extends TestCase
{
    use RefreshDatabase;

    /** The demo user: the auth stub resolves the lowest user id. */
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_it_marks_every_alert_for_the_homes_of_the_given_ids_read(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();
        $older = Alert::factory()->for($this->user)->create(['listing_id' => $listing->id, 'updated_at' => now()->subDays(2)]);
        $latest = Alert::factory()->for($this->user)->create(['listing_id' => $listing->id, 'updated_at' => now()->subDay()]);

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$latest->id], 'read' => true]);

        // Assert
        $response->assertRedirect();
        $this->assertNotNull($older->fresh()->read_at);
        $this->assertNotNull($latest->fresh()->read_at);
    }

    public function test_it_leaves_other_homes_and_other_users_alone(): void
    {
        // Arrange
        $target = Alert::factory()->for($this->user)->create();
        $otherHome = Alert::factory()->for($this->user)->create();
        $otherUser = Alert::factory()->create(['listing_id' => $target->listing_id]);

        // Act
        $this->patch(route('alerts.read'), ['alert_ids' => [$target->id], 'read' => true]);

        // Assert
        $this->assertNull($otherHome->fresh()->read_at);
        $this->assertNull($otherUser->fresh()->read_at);
    }

    public function test_the_unread_count_drops_afterwards(): void
    {
        // Arrange
        $alert = Alert::factory()->for($this->user)->create();
        Alert::factory()->for($this->user)->create();
        $this->patch(route('alerts.read'), ['alert_ids' => [$alert->id], 'read' => true]);

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('alerts.unread', 1));
    }

    public function test_it_does_not_touch_updated_at_so_the_list_order_is_stable(): void
    {
        // Arrange
        $newer = Alert::factory()->for($this->user)->create(['updated_at' => now()->subMinute()]);
        $older = Alert::factory()->for($this->user)->create(['updated_at' => now()->subHour()]);
        $stamp = $older->updated_at->toIso8601String();
        $this->patch(route('alerts.read'), ['alert_ids' => [$older->id], 'read' => true]);

        // Act
        $response = $this->get(route('alerts.index'));

        // Assert
        $this->assertSame($stamp, $older->fresh()->updated_at->toIso8601String());
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('alertList.data.0.id', $newer->id));
    }

    public function test_another_users_alert_is_refused_and_nothing_changes(): void
    {
        // Arrange
        $mine = Alert::factory()->for($this->user)->create();
        $theirs = Alert::factory()->create();

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$mine->id, $theirs->id], 'read' => true]);

        // Assert
        $response->assertNotFound();
        $this->assertNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_an_unknown_id_is_refused_the_same_way(): void
    {
        // Arrange
        $mine = Alert::factory()->for($this->user)->create();

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$mine->id, 999_999], 'read' => true]);

        // Assert
        $response->assertNotFound();
        $this->assertNull($mine->fresh()->read_at);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'no ids' => [['read' => true], 'alert_ids'],
            'empty ids' => [['alert_ids' => [], 'read' => true], 'alert_ids'],
            'too many ids' => [['alert_ids' => range(1, 16), 'read' => true], 'alert_ids'],
            'non-integer id' => [['alert_ids' => ['abc'], 'read' => true], 'alert_ids.0'],
            'duplicate ids' => [['alert_ids' => [1, 1], 'read' => true], 'alert_ids.0'],
            'ids not an array' => [['alert_ids' => 1, 'read' => true], 'alert_ids'],
            'read missing' => [['alert_ids' => [1]], 'read'],
            'read false' => [['alert_ids' => [1], 'read' => false], 'read'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected(array $payload, string $field): void
    {
        // Arrange
        $alert = Alert::factory()->for($this->user)->create();

        // Act
        $response = $this->patch(route('alerts.read'), $payload);

        // Assert
        $response->assertSessionHasErrors($field);
        $this->assertNull($alert->fresh()->read_at);
    }

    public function test_marking_read_is_throttled_with_a_friendly_message(): void
    {
        // Arrange
        $alert = Alert::factory()->for($this->user)->create();
        foreach (range(1, 30) as $i) {
            $this->patch(route('alerts.read'), ['alert_ids' => [$alert->id], 'read' => true]);
        }

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$alert->id], 'read' => true]);

        // Assert
        $response->assertRedirect()->assertSessionHasErrors('criteria');
    }

    public function test_non_positive_alert_ids_are_rejected(): void
    {
        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [0, -1], 'read' => true]);

        // Assert
        $response->assertSessionHasErrors(['alert_ids.0', 'alert_ids.1']);
    }
}
