<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Notification::fake();

        $this->customer = tap(User::factory()->create([
            'email' => 'sok@example.com',
            'password' => Hash::make('Password123!'),
        ]))->assignRole(Role::Customer->value);
    }

    public function test_a_reset_link_is_sent(): void
    {
        $this->postJson('/api/v1/forgot-password', ['email' => 'sok@example.com'])
            ->assertOk();

        Notification::assertSentTo($this->customer, ResetPassword::class);
    }

    public function test_an_unknown_address_gets_the_same_response(): void
    {
        // A differing response would let anyone enumerate customer emails.
        $known = $this->postJson('/api/v1/forgot-password', ['email' => 'sok@example.com']);
        $unknown = $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com']);

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json('message'), $unknown->json('message'));

        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_the_link_points_at_the_storefront(): void
    {
        config(['app.frontend_url' => 'https://shop.example.com']);

        $this->postJson('/api/v1/forgot-password', ['email' => 'sok@example.com']);

        Notification::assertSentTo($this->customer, ResetPassword::class, function ($notification) {
            $url = $notification->toMail($this->customer)->actionUrl;

            // This app is the admin panel; the reset screen lives elsewhere.
            return str_starts_with($url, 'https://shop.example.com/reset-password?token=')
                && str_contains($url, 'email=sok%40example.com');
        });
    }

    public function test_a_password_can_be_reset_with_a_valid_token(): void
    {
        $token = app('auth.password.broker')->createToken($this->customer);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'sok@example.com',
            'password' => 'BrandNew456!',
            'password_confirmation' => 'BrandNew456!',
        ])->assertOk();

        $this->assertTrue(Hash::check('BrandNew456!', $this->customer->fresh()->password));
    }

    public function test_an_invalid_token_is_refused(): void
    {
        $this->postJson('/api/v1/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'sok@example.com',
            'password' => 'BrandNew456!',
            'password_confirmation' => 'BrandNew456!',
        ])->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('Password123!', $this->customer->fresh()->password));
    }

    public function test_a_reset_revokes_every_existing_token(): void
    {
        $this->customer->createToken('phone');
        $this->customer->createToken('laptop');

        $token = app('auth.password.broker')->createToken($this->customer);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'sok@example.com',
            'password' => 'BrandNew456!',
            'password_confirmation' => 'BrandNew456!',
        ])->assertOk();

        // A reset is recovery from possible compromise — nothing stays signed in.
        $this->assertSame(0, $this->customer->fresh()->tokens()->count());
    }

    public function test_a_weak_password_is_refused(): void
    {
        $token = app('auth.password.broker')->createToken($this->customer);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'sok@example.com',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertJsonValidationErrors('password');
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->postJson('/api/v1/forgot-password', ['email' => 'sok@example.com']);
        }

        $this->postJson('/api/v1/forgot-password', ['email' => 'sok@example.com'])
            ->assertStatus(429);
    }
}
