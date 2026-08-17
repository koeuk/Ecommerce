<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserAddress;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->customer = tap(User::factory()->create())->assignRole(Role::Customer->value);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'receiver_name' => 'Sok Dara',
            'phone' => '012 345 678',
            'address_line1' => '12 Street 240',
            'city' => 'Phnom Penh',
            'state' => 'Phnom Penh',
            'country_code' => 'KH',
        ], $overrides);
    }

    public function test_the_address_book_requires_authentication(): void
    {
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
        $this->postJson('/api/v1/addresses', $this->payload())->assertUnauthorized();
    }

    public function test_a_customer_can_add_an_address(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/addresses', $this->payload(['label' => 'Home']))
            ->assertCreated()
            ->assertJsonPath('data.label', 'Home')
            ->assertJsonPath('data.full_address', '12 Street 240, Phnom Penh, Phnom Penh');

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $this->customer->id,
            'address_line1' => '12 Street 240',
        ]);
    }

    public function test_the_first_address_becomes_the_default(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/addresses', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.is_default_shipping', true)
            ->assertJsonPath('data.is_default_billing', true);
    }

    public function test_only_one_address_can_be_the_default(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/addresses', $this->payload());

        $second = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/addresses', $this->payload([
                'address_line1' => '99 Street 271',
                'is_default_shipping' => true,
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(
            1,
            UserAddress::where('user_id', $this->customer->id)
                ->where('is_default_shipping', true)
                ->count()
        );

        $this->assertTrue(UserAddress::find($second)->is_default_shipping);
    }

    public function test_an_address_can_be_updated(): void
    {
        $id = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/addresses', $this->payload())
            ->json('data.id');

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/addresses/{$id}", $this->payload(['receiver_name' => 'Dara Sok']))
            ->assertOk()
            ->assertJsonPath('data.receiver_name', 'Dara Sok');
    }

    public function test_an_address_can_be_removed(): void
    {
        $id = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/addresses', $this->payload())
            ->json('data.id');

        $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$id}")
            ->assertOk();

        $this->assertSoftDeleted('user_addresses', ['id' => $id]);
    }

    public function test_one_customer_cannot_reach_anothers_address(): void
    {
        $other = tap(User::factory()->create())->assignRole(Role::Customer->value);

        $id = $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/addresses', $this->payload())
            ->json('data.id');

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/addresses/{$id}", $this->payload())
            ->assertNotFound();

        $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$id}")
            ->assertNotFound();

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/addresses')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/addresses', ['label' => 'Home'])
            ->assertJsonValidationErrors([
                'receiver_name', 'phone', 'address_line1', 'city', 'state',
            ]);
    }
}
