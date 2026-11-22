<?php

namespace Tests\Feature\Hardening;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Broken access control: a guest, another user's record, an id that does not
 * exist and an id one past the user's own must all be refused (401 or 404) with
 * the other user's data untouched.
 */
class OwnershipTest extends TestCase
{
    use CapturesLogs;
    use RefreshDatabase;

    /** The demo user: the auth stub resolves the lowest user id. */
    private User $user;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Zed Zebra', 'email' => 'zed@zebra.test']);
        $this->other = User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);
        $this->captureLogs();
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function userScopedRoutes(): array
    {
        return [
            'alerts index' => ['get', '/alerts', []],
            'alerts read' => ['patch', '/alerts', ['alert_ids' => [1], 'read' => true]],
            'saved searches index' => ['get', '/saved-searches', []],
            'saved searches store' => ['post', '/saved-searches', ['min_price' => 5]],
            'saved searches destroy' => ['delete', '/saved-searches/1', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('userScopedRoutes')]
    public function test_a_guest_gets_401_and_nothing_is_written_or_logged(string $method, string $uri, array $data): void
    {
        // Arrange: nobody exists, so the stub resolves no user.
        User::query()->delete();
        $this->captureLogs();

        // Act
        $response = $this->call($method, $uri, $data);

        // Assert
        $response->assertUnauthorized();
        $this->assertSame(0, SavedSearch::query()->count());
        $this->assertSame([], $this->logged);
    }

    public function test_deleting_another_users_search_is_a_404_and_keeps_it(): void
    {
        // Arrange
        $theirs = SavedSearch::factory()->for($this->other)->create();

        // Act
        $response = $this->delete(route('saved-searches.destroy', $theirs->id));

        // Assert
        $response->assertNotFound();
        $this->assertNotNull($theirs->fresh());
        $this->assertNotLogged('saved_search.deleted');
    }

    public function test_guessing_the_id_after_your_own_is_a_404(): void
    {
        // Arrange
        $mine = SavedSearch::factory()->for($this->user)->create();
        $theirs = SavedSearch::factory()->for($this->other)->create(['id' => $mine->id + 1]);

        // Act
        $response = $this->delete(route('saved-searches.destroy', $mine->id + 1));

        // Assert
        $response->assertNotFound();
        $this->assertNotNull($theirs->fresh());
        $this->assertNotNull($mine->fresh());
    }

    public function test_deleting_a_search_that_does_not_exist_is_a_404_and_logs_nothing(): void
    {
        // Act
        $response = $this->delete(route('saved-searches.destroy', 999_999));

        // Assert
        $response->assertNotFound();
        $this->assertNotLogged('saved_search.deleted');
    }

    public function test_marking_another_users_alert_read_is_a_404_with_a_security_warning(): void
    {
        // Arrange
        $theirs = Alert::factory()->for($this->other)->create();

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$theirs->id], 'read' => true]);

        // Assert
        $response->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
        $log = $this->assertLoggedOnce('warning', 'Alert ids not owned by user', ['user_id', 'requested', 'found']);
        $this->assertSame([$this->user->id, 1, 0], array_values($log->context));
        $this->assertNotLogged('alerts.marked_read');
        $this->assertNoPersonalData();
    }

    public function test_marking_a_non_existent_alert_read_is_the_same_404_and_warning(): void
    {
        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [999_999], 'read' => true]);

        // Assert
        $response->assertNotFound();
        $log = $this->assertLoggedOnce('warning', 'Alert ids not owned by user', ['user_id', 'requested', 'found']);
        $this->assertSame([$this->user->id, 1, 0], array_values($log->context));
    }

    public function test_guessing_the_alert_id_after_your_own_changes_nothing(): void
    {
        // Arrange
        $mine = Alert::factory()->for($this->user)->create();
        $theirs = Alert::factory()->for($this->other)->create(['id' => $mine->id + 1]);

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$theirs->id], 'read' => true]);

        // Assert
        $response->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
        $this->assertNull($mine->fresh()->read_at);
    }

    public function test_a_mix_of_own_and_foreign_alert_ids_changes_nothing(): void
    {
        // Arrange
        $mine = Alert::factory()->for($this->user)->create();
        $theirs = Alert::factory()->for($this->other)->create();

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$mine->id, $theirs->id], 'read' => true]);

        // Assert: atomic, so even the user's own alert stays unread.
        $response->assertNotFound();
        $this->assertNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_a_deleted_alert_id_is_a_404_and_the_rest_stay_unread(): void
    {
        // Arrange
        $mine = Alert::factory()->for($this->user)->create();
        $gone = Alert::factory()->for($this->user)->create();
        $goneId = $gone->id;
        $gone->delete();

        // Act
        $response = $this->patch(route('alerts.read'), ['alert_ids' => [$mine->id, $goneId], 'read' => true]);

        // Assert
        $response->assertNotFound();
        $this->assertNull($mine->fresh()->read_at);
        $this->assertLoggedOnce('warning', 'Alert ids not owned by user', ['user_id', 'requested', 'found']);
    }

    public function test_a_user_id_in_the_body_cannot_save_a_search_for_someone_else(): void
    {
        // Act
        $this->post(route('saved-searches.store'), ['min_price' => 5, 'user_id' => $this->other->id]);

        // Assert
        $this->assertSame(1, $this->user->savedSearches()->count());
        $this->assertSame(0, $this->other->savedSearches()->count());
    }

    public function test_the_index_pages_only_show_the_users_own_records(): void
    {
        // Arrange
        SavedSearch::factory()->for($this->user)->create();
        SavedSearch::factory()->for($this->other)->count(3)->create();
        Alert::factory()->for($this->user)->create();
        Alert::factory()->for($this->other)->count(3)->create();

        // Act
        $searches = $this->get(route('saved-searches.index'));
        $alerts = $this->get(route('alerts.index'));

        // Assert
        $searches->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearchCount', 1));
        $alerts->assertInertia(fn (AssertableInertia $page) => $page->has('alertList.data', 1));
    }
}
