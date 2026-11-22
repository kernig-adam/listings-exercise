<?php

namespace Tests\Feature;

use App\Http\Controllers\AlertController;
use App\Http\Controllers\SavedSearchController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RequireUser is the first line of defence; these routes skip it to prove the
 * controllers themselves answer 401, not a null-pointer 500, if it is ever missed.
 */
class GuestGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function userScopedActions(): array
    {
        return [
            'alerts index' => ['get', 'alerts', []],
            'alerts mark read' => ['patch', 'alerts', ['alert_ids' => [1], 'read' => true]],
            'saved searches index' => ['get', 'searches', []],
            'saved searches store' => ['post', 'searches', ['min_price' => 100000]],
            'saved searches destroy' => ['delete', 'searches/1', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('userScopedActions')]
    public function test_user_scoped_controller_methods_return_401_without_a_user(string $method, string $uri, array $data): void
    {
        // Arrange: no user is seeded, so the stub resolves nobody, and no RequireUser.
        Route::get('alerts', [AlertController::class, 'index']);
        Route::patch('alerts', [AlertController::class, 'markRead']);
        Route::get('searches', [SavedSearchController::class, 'index']);
        Route::post('searches', [SavedSearchController::class, 'store']);
        Route::delete('searches/{id}', [SavedSearchController::class, 'destroy']);

        // Act
        $response = $this->call($method, $uri, $data);

        // Assert
        $response->assertUnauthorized();
    }
}
