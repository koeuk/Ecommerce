<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_customer_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Sok Dara',
            'email' => 'sok@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $user = User::firstWhere('email', 'sok@example.com');

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(Role::Customer->value));
        $this->assertTrue($user->is_active);
        $this->assertNotSame('Password123!', $user->password);   // hashed
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'sok@example.com']);

        $this->postJson('/api/v1/register', [
            'name' => 'Sok Dara',
            'email' => 'sok@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Sok Dara',
            'email' => 'sok@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Different123!',
        ])->assertJsonValidationErrors('password');
    }

    public function test_a_customer_can_log_in(): void
    {
        $user = tap(User::factory()->create([
            'email' => 'sok@example.com',
            'password' => Hash::make('Password123!'),
        ]))->assignRole(Role::Customer->value);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'sok@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_bad_credentials_are_rejected(): void
    {
        tap(User::factory()->create([
            'email' => 'sok@example.com',
            'password' => Hash::make('Password123!'),
        ]))->assignRole(Role::Customer->value);

        $this->postJson('/api/v1/login', [
            'email' => 'sok@example.com',
            'password' => 'wrong-password',
        ])->assertJsonValidationErrors('email');
    }

    public function test_an_unknown_email_gives_the_same_error_as_a_bad_password(): void
    {
        // Identical responses, so this cannot be used to enumerate accounts.
        $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ])->assertJsonValidationErrors('email');
    }

    public function test_a_deactivated_account_cannot_log_in(): void
    {
        tap(User::factory()->create([
            'email' => 'sok@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => false,
        ]))->assignRole(Role::Customer->value);

        $this->postJson('/api/v1/login', [
            'email' => 'sok@example.com',
            'password' => 'Password123!',
        ])->assertJsonValidationErrors('email');

        $this->assertSame(0, User::firstWhere('email', 'sok@example.com')->tokens()->count());
    }

    public function test_an_admin_cannot_log_in_through_the_storefront(): void
    {
        tap(User::factory()->create([
            'email' => 'boss@example.com',
            'password' => Hash::make('Password123!'),
        ]))->assignRole(Role::Manager->value);

        // Admins use the session-based admin panel; the two never mix.
        $this->postJson('/api/v1/login', [
            'email' => 'boss@example.com',
            'password' => 'Password123!',
        ])->assertJsonValidationErrors('email');

        $this->assertSame(0, User::firstWhere('email', 'boss@example.com')->tokens()->count());
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_customer(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Customer->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Customer->value);
        $user->createToken('other-device');
        $current = $user->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->postJson('/api/v1/logout')
            ->assertOk();

        // The other device stays signed in.
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_logout_all_revokes_every_token(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Customer->value);
        $user->createToken('other-device');
        $current = $user->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->postJson('/api/v1/logout-all')
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_login_is_rate_limited(): void
    {
        // 10/min keyed on email + IP.
        foreach (range(1, 10) as $ignored) {
            $this->postJson('/api/v1/login', ['email' => 'sok@example.com', 'password' => 'nope']);
        }

        $this->postJson('/api/v1/login', ['email' => 'sok@example.com', 'password' => 'nope'])
            ->assertStatus(429);
    }
}
