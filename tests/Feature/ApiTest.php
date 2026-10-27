<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost'], 'session.secure' => false]);
        $this->withHeader('Origin', 'http://localhost');
    }

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Interview User',
            'email' => 'interview@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ], $overrides);
    }

    public function test_registration_hashes_password_and_starts_a_new_session(): void
    {
        $this->getJson('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $oldSession = session()->getId();

        $response = $this->postJson('/api/register', $this->registration());

        $response->assertCreated()->assertJsonPath('data.email', 'interview@example.test')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $user = User::where('email', 'interview@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_accepts_valid_credentials_and_rotates_the_session(): void
    {
        $user = User::factory()->create(['password' => 'StrongPassword123!']);
        $this->getJson('/sanctum/csrf-cookie');
        $oldSession = session()->getId();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'StrongPassword123!'])
            ->assertOk()->assertJsonPath('data.id', $user->id);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($oldSession, session()->getId());
    }

    public function test_invalid_login_returns_422_and_leaves_the_user_unauthenticated(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

        $this->assertGuest('web');
    }

    public function test_logout_invalidates_the_session_and_rotates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->withSession(['private_value' => 'secret']);
        $oldSession = session()->getId();
        $oldToken = session()->token();

        $this->postJson('/api/logout')->assertOk()->assertSessionMissing('private_value');

        $this->assertGuest('web');
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        Auth::forgetGuards();
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public static function protectedEndpoints(): array
    {
        return [
            'profile read' => ['GET', '/api/profile'],
            'profile update' => ['PATCH', '/api/profile'],
            'user alias' => ['GET', '/api/user'],
            'logout' => ['POST', '/api/logout'],
            'list' => ['GET', '/api/products'],
            'create' => ['POST', '/api/products'],
            'read' => ['GET', '/api/products/1'],
            'patch' => ['PATCH', '/api/products/1'],
            'put' => ['PUT', '/api/products/1'],
            'delete' => ['DELETE', '/api/products/1'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_protected_endpoints_return_json_401_for_guests(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_missing_or_untrusted_origin_returns_json_403(): void
    {
        $this->flushHeaders()->postJson('/api/login', [])->assertForbidden();
        $this->withHeader('Origin', 'http://untrusted.test')->postJson('/api/login', [])
            ->assertForbidden()->assertJsonStructure(['message']);
        $this->assertGuest('web');
    }

    public function test_profile_can_be_viewed_and_updated_without_changing_protected_attributes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user, 'web')->getJson('/api/profile')->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);

        $this->patchJson('/api/profile', ['name' => 'New Name', 'email' => 'new@example.test', 'id' => $other->id, 'email_verified_at' => now()->toISOString()])
            ->assertOk()->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.test', 'email_verified_at' => null]);
        $this->assertModelExists($other);
    }

    public function test_profile_unique_email_ignores_self_but_rejects_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user, 'web')->patchJson('/api/profile', ['email' => $user->email])->assertOk();

        $this->patchJson('/api/profile', ['email' => $other->email])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_password_update_requires_current_password_and_hashes_the_new_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123!']);
        $this->actingAs($user, 'web');
        $payload = ['password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];

        $this->patchJson('/api/profile', $payload)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/profile', [...$payload, 'current_password' => 'incorrect'])->assertUnprocessable();
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));

        $this->patchJson('/api/profile', [...$payload, 'current_password' => 'OldPassword123!'])->assertOk()->assertJsonMissingPath('data.password');
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_product_crud_persists_only_the_current_users_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $payload = ['name' => 'Keyboard', 'description' => 'USB keyboard', 'price' => '125.50', 'stock' => 4];

        $response = $this->postJson('/api/products', $payload)->assertCreated()
            ->assertJsonPath('data.price', '125.50')->assertJsonPath('data.stock', 4);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('products', ['id' => $id, 'user_id' => $user->id, ...$payload]);
        $this->getJson('/api/products/'.$id)->assertOk()->assertJsonPath('data.name', 'Keyboard');
        $this->getJson('/api/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->patchJson('/api/products/'.$id, ['price' => '99.99', 'description' => null])->assertOk()->assertJsonPath('data.price', '99.99');
        $this->assertDatabaseHas('products', ['id' => $id, 'price' => '99.99', 'description' => null]);
        $this->putJson('/api/products/'.$id, ['stock' => 0])->assertOk()->assertJsonPath('data.stock', 0);
        $this->deleteJson('/api/products/'.$id)->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $id]);
        $this->getJson('/api/products/'.$id)->assertNotFound();
    }

    public function test_users_cannot_list_read_modify_or_delete_another_users_products(): void
    {
        $owner = User::factory()->create();
        $product = $owner->products()->create(['name' => 'Private Product', 'price' => '10.00', 'stock' => 2]);
        $this->actingAs(User::factory()->create(), 'web');

        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->patchJson('/api/products/'.$product->id, ['name' => 'Stolen'])->assertNotFound();
        $this->putJson('/api/products/'.$product->id, ['price' => '1.00'])->assertNotFound();
        $this->deleteJson('/api/products/'.$product->id)->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'user_id' => $owner->id, 'name' => 'Private Product', 'price' => '10.00']);
    }

    public function test_products_are_paginated_and_missing_ids_return_json_404(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 16; $i++) {
            $user->products()->create(['name' => 'Product '.$i, 'price' => 1]);
        }
        $this->actingAs($user, 'web');

        $this->getJson('/api/products')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 16);
        $this->getJson('/api/products?page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/products/999999')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/products/invalid')->assertNotFound()->assertJsonStructure(['message']);
    }

    public static function invalidProducts(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'long name' => [['name' => str_repeat('a', 256)], 'name'],
            'negative price' => [['price' => -1], 'price'],
            'overflow price' => [['price' => '100000000.00'], 'price'],
            'excess precision' => [['price' => '1.234'], 'price'],
            'negative stock' => [['stock' => -1], 'stock'],
            'fractional stock' => [['stock' => 1.5], 'stock'],
            'overflow stock' => [['stock' => 4294967296], 'stock'],
            'owner injection' => [['user_id' => 1], 'user_id'],
            'invalid description' => [['description' => []], 'description'],
        ];
    }

    #[DataProvider('invalidProducts')]
    public function test_invalid_product_returns_422_without_creating_a_record(array $invalid, string $field): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->postJson('/api/products', array_replace(['name' => 'Product', 'price' => '1.00', 'stock' => 0], $invalid))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_product_update_preserves_the_existing_record(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'Product', 'price' => '10.00']);
        $this->actingAs($user, 'web');

        $this->patchJson('/api/products/'.$product->id, ['price' => -1, 'user_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors(['price', 'user_id']);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'user_id' => $user->id, 'price' => '10.00']);
    }

    public function test_registration_and_login_validation_return_json_422(): void
    {
        $this->postJson('/api/register', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/register', $this->registration(['password_confirmation' => 'different']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_registration_returns_422_without_creating_a_user(): void
    {
        User::factory()->create(['email' => 'interview@example.test']);

        $this->postJson('/api/register', $this->registration())->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest('web');
    }

    public function test_login_rate_limit_returns_429_and_expires(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'MISSING@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests()->assertHeader('Retry-After');

        $this->travel(61)->seconds();
        $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }

    public function test_registration_rate_limit_returns_429(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/register', [])->assertTooManyRequests()->assertHeader('Retry-After');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_csrf_rejects_missing_or_invalid_tokens_and_accepts_the_xsrf_cookie(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $cookies = $this->getJson('/sanctum/csrf-cookie')->assertNoContent();
        $encryptedToken = $cookies->getCookie('XSRF-TOKEN', false)->getValue();
        $this->withCredentials()->withUnencryptedCookie(config('session.cookie'), $cookies->getCookie(config('session.cookie'), false)->getValue());

        $this->postJson('/api/register', $this->registration())->assertStatus(419)->assertJsonStructure(['message']);
        $this->postJson('/api/register', $this->registration(), ['X-XSRF-TOKEN' => 'invalid'])->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
        $registered = $this->postJson('/api/register', $this->registration(), ['X-XSRF-TOKEN' => $encryptedToken])->assertCreated();
        $this->withUnencryptedCookie(config('session.cookie'), $registered->getCookie(config('session.cookie'), false)->getValue());
        Auth::forgetGuards();
        $this->getJson('/api/profile')->assertOk()->assertJsonPath('data.email', 'interview@example.test');
        $this->postJson('/api/products', ['name' => 'CSRF product', 'price' => 2])->assertStatus(419);
        $this->postJson('/api/logout')->assertStatus(419);
        $this->assertDatabaseCount('products', 0);
        $this->assertAuthenticated('web');

        $currentToken = $registered->getCookie('XSRF-TOKEN', false)->getValue();
        $product = $this->postJson('/api/products', ['name' => 'CSRF product', 'price' => 2], ['X-XSRF-TOKEN' => $currentToken])
            ->assertCreated();
        $id = $product->json('data.id');
        $this->patchJson('/api/products/'.$id, ['price' => 3])->assertStatus(419);
        $this->deleteJson('/api/products/'.$id)->assertStatus(419);
        $this->patchJson('/api/profile', ['name' => 'Changed'])->assertStatus(419);
        $this->assertDatabaseHas('products', ['id' => $id, 'price' => 2]);

        $this->postJson('/api/logout', [], ['X-XSRF-TOKEN' => $currentToken])->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_minimal_product_returns_database_defaults_and_zero_price_is_allowed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->postJson('/api/products', ['name' => 'Free sample', 'price' => 0])
            ->assertCreated()->assertJsonPath('data.stock', 0)->assertJsonPath('data.price', '0.00');

        $this->assertDatabaseHas('products', ['user_id' => $user->id, 'stock' => 0, 'price' => 0]);
    }

    public static function invalidRegistration(): array
    {
        return [
            'empty name' => [['name' => ''], 'name'],
            'long name' => [['name' => str_repeat('a', 256)], 'name'],
            'invalid email' => [['email' => 'invalid'], 'email'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'long password' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
        ];
    }

    #[DataProvider('invalidRegistration')]
    public function test_invalid_registration_returns_422_without_creating_a_user(array $invalid, string $field): void
    {
        $this->postJson('/api/register', $this->registration($invalid))->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest('web');
    }

    public function test_invalid_profile_update_returns_422_and_preserves_the_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->patchJson('/api/profile', ['name' => '', 'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'different'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'current_password']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'password' => $user->password]);
    }

    public function test_login_rejects_non_string_credentials_with_json_422(): void
    {
        $this->postJson('/api/login', ['email' => ['invalid'], 'password' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);

        $this->assertGuest('web');
    }

    public function test_login_ip_limit_blocks_email_rotation(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/login', ['email' => 'attempt'.$i.'@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'another@example.test', 'password' => 'wrong'])->assertTooManyRequests();

        $this->assertGuest('web');
    }
}
