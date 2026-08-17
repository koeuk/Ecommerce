<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->manager = tap(User::factory()->create())->assignRole(Role::Manager->value);
    }

    public function test_manager_can_create_a_brand(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.brands.store'), [
                'name' => ['en' => 'Asus', 'km' => 'អាស៊ុស'],
                'is_active' => true,
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.brands.index'));

        $brand = Brand::firstWhere('slug', 'asus');

        $this->assertNotNull($brand);
        $this->assertSame('Asus', $brand->getTranslation('name', 'en'));
        $this->assertSame('អាស៊ុស', $brand->getTranslation('name', 'km'));
        $this->assertTrue($brand->is_active);
        $this->assertSame(3, $brand->sort_order);
    }

    public function test_a_logo_upload_is_stored(): void
    {
        $this->actingAs($this->manager)->post(route('admin.brands.store'), [
            'name' => ['en' => 'Asus'],
            'logo' => UploadedFile::fake()->image('asus.png'),
        ]);

        $brand = Brand::firstWhere('slug', 'asus');

        $this->assertNotNull($brand->logo);
        Storage::disk('public')->assertExists($brand->logo);
    }

    public function test_replacing_the_logo_deletes_the_old_file(): void
    {
        $this->actingAs($this->manager)->post(route('admin.brands.store'), [
            'name' => ['en' => 'Asus'],
            'logo' => UploadedFile::fake()->image('old.png'),
        ]);

        $brand = Brand::firstWhere('slug', 'asus');
        $oldPath = $brand->logo;

        $this->actingAs($this->manager)->put(route('admin.brands.update', $brand), [
            'name' => ['en' => 'Asus'],
            'logo' => UploadedFile::fake()->image('new.png'),
        ]);

        $newPath = $brand->fresh()->logo;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_a_brand_still_holding_products_cannot_be_deleted(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->forBrand($brand)->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.brands.destroy', $brand))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'deleted_at' => null]);
    }

    public function test_an_unused_brand_can_be_deleted(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.brands.destroy', $brand))
            ->assertRedirect(route('admin.brands.index'));

        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
    }

    public function test_the_english_name_is_required(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.brands.store'), ['name' => ['km' => 'ខ្មែរ']])
            ->assertSessionHasErrors('name.en');
    }

    public function test_slugs_are_unique_across_brands(): void
    {
        Brand::factory()->create(['slug' => 'asus']);

        $this->actingAs($this->manager)
            ->post(route('admin.brands.store'), ['name' => ['en' => 'Asus'], 'slug' => 'asus'])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_brand_can_keep_its_own_slug_on_update(): void
    {
        $brand = Brand::factory()->create(['slug' => 'asus']);

        $this->actingAs($this->manager)
            ->put(route('admin.brands.update', $brand), [
                'name' => ['en' => 'Asus Global'],
                'slug' => 'asus',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Asus Global', $brand->fresh()->getTranslation('name', 'en'));
    }

    public function test_index_can_be_filtered_by_status(): void
    {
        Brand::factory()->create(['name' => ['en' => 'Active One']]);
        Brand::factory()->inactive()->create(['name' => ['en' => 'Inactive One']]);

        $this->actingAs($this->manager)
            ->get(route('admin.brands.index', ['filter' => ['status' => 'active']]))
            ->assertOk();
    }

    public function test_staff_cannot_create_brands(): void
    {
        $staff = tap(User::factory()->create())->assignRole(Role::Staff->value);

        $this->actingAs($staff)
            ->post(route('admin.brands.store'), ['name' => ['en' => 'Nope']])
            ->assertForbidden();
    }
}
