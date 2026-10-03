<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public static function protectedEndpoints(): array
    {
        return [
            'list purchase orders' => ['GET', '/api/purchase-orders'],
            'create purchase order' => ['POST', '/api/purchase-orders'],
            'show purchase order' => ['GET', '/api/purchase-orders/1'],
            'update purchase order' => ['PUT', '/api/purchase-orders/1'],
            'update status' => ['PATCH', '/api/purchase-orders/1/status'],
            'inventory' => ['GET', '/api/inventory'],
            'low stock' => ['GET', '/api/inventory/low-stock'],
            'supplier spend' => ['GET', '/api/reports/supplier-spend'],
            'suppliers' => ['GET', '/api/suppliers'],
            'me' => ['GET', '/api/me'],
        ];
    }

    #[Test]
    #[DataProvider('protectedEndpoints')]
    public function requests_without_a_token_are_rejected_with_401(string $method, string $uri): void
    {
        $this->json($method, $uri, ['supplier_id' => 1, 'items' => []])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function a_401_json_response_is_returned_even_without_an_accept_header(): void
    {
        $this->post('/api/purchase-orders', [])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->get('/api/purchase-orders')->assertUnauthorized();
    }

    #[Test]
    public function an_invalid_bearer_token_is_rejected(): void
    {
        $this->withToken('1|not-a-real-token')
            ->getJson('/api/purchase-orders')
            ->assertUnauthorized();
    }

    #[Test]
    public function a_user_can_log_in_and_use_the_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'scanner@example.com', 'password' => 'secret-pass']);

        $response = $this->postJson('/api/login', [
            'email' => 'scanner@example.com',
            'password' => 'secret-pass',
            'device_name' => 'Handheld #7',
        ])
            ->assertOk()
            ->assertJsonStructure(['token_type', 'access_token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'scanner@example.com');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Handheld #7',
        ]);

        $this->withToken($response->json('access_token'))
            ->getJson('/api/purchase-orders')
            ->assertOk();
    }

    #[Test]
    public function login_fails_with_wrong_credentials(): void
    {
        User::factory()->create(['email' => 'scanner@example.com', 'password' => 'secret-pass']);

        $this->postJson('/api/login', ['email' => 'scanner@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);

        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'secret-pass'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function login_validates_required_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
