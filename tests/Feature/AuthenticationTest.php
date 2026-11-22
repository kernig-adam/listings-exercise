<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Authentication, not authorisation: with no user at all (the auth stub returns
 * null, the same shape as a guest), user-scoped routes must refuse cleanly with
 * a 401 rather than crash on a null user. Whose-data checks live in the
 * saved-search and alert tests.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function userScopedRoutes(): array
    {
        return [
            'alerts' => ['get', '/alerts'],
            'saved searches' => ['get', '/saved-searches'],
            'save a search' => ['post', '/saved-searches'],
            'delete a search' => ['delete', '/saved-searches/1'],
        ];
    }

    #[DataProvider('userScopedRoutes')]
    public function test_user_scoped_routes_refuse_a_guest(string $method, string $uri): void
    {
        // Arrange
        // No user is seeded, so the auth stub resolves no one: a guest.

        // Act
        $response = $this->{$method}($uri, ['region' => 'Leeds']);

        // Assert
        $response->assertUnauthorized();
    }

    public function test_listings_stay_public_for_a_guest(): void
    {
        // Arrange
        $listing = Listing::factory()->live()->create();

        // Act
        $index = $this->get('/');
        $show = $this->get(route('listings.show', $listing));

        // Assert
        $index->assertOk();
        $show->assertOk();
    }
}
