<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['en' => 'Gaming Laptop'],
            'sku' => 'LAP-001',
            'price' => 1499.00,
            'status' => ProductStatus::Published->value,
            'condition' => 'new',
        ], $overrides);
    }

    public function test_uploaded_images_are_stored_and_the_first_becomes_primary(): void
    {
        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload([
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ]));

        $product = Product::firstWhere('sku', 'LAP-001');
        $images = $product->images()->orderBy('sort_order')->get();

        $this->assertCount(2, $images);

        // Only the first upload is primary; sort_order starts at 1.
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());

        // Alt text defaults to the English title.
        $this->assertSame('Gaming Laptop', $images[0]->alt_text);

        foreach ($images as $image) {
            // Uploads are converted to WebP, and each gets a thumbnail.
            $this->assertStringEndsWith('.webp', $image->path);
            $this->assertStringEndsWith('-thumb.webp', $image->thumbnail_path);

            Storage::disk('public')->assertExists($image->path);
            Storage::disk('public')->assertExists($image->thumbnail_path);
        }
    }

    public function test_a_large_upload_is_scaled_down(): void
    {
        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('huge.jpg', 4000, 3000)],
        ]));

        $image = Product::firstWhere('sku', 'LAP-001')->images()->first();

        $stored = Storage::disk('public')->get($image->path);
        [$width] = getimagesizefromstring($stored);

        $this->assertSame(1600, $width);

        [$thumbWidth] = getimagesizefromstring(Storage::disk('public')->get($image->thumbnail_path));
        $this->assertSame(400, $thumbWidth);
    }

    public function test_a_small_upload_is_not_upscaled(): void
    {
        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('small.jpg', 200, 150)],
        ]));

        $image = Product::firstWhere('sku', 'LAP-001')->images()->first();

        [$width] = getimagesizefromstring(Storage::disk('public')->get($image->path));

        // scaleDown only ever shrinks — a small image must not be inflated.
        $this->assertSame(200, $width);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload([
                'images' => [UploadedFile::fake()->create('malware.php', 16)],
            ]))
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_images_can_be_reordered(): void
    {
        $product = Product::factory()->create();
        $a = ProductImage::create(['product_id' => $product->id, 'path' => 'a.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $b = ProductImage::create(['product_id' => $product->id, 'path' => 'b.jpg', 'sort_order' => 2, 'is_primary' => false]);
        $c = ProductImage::create(['product_id' => $product->id, 'path' => 'c.jpg', 'sort_order' => 3, 'is_primary' => false]);

        $this->actingAs($this->manager)->put(
            route('admin.products.images.reorder', $product),
            ['ids' => [$c->id, $a->id, $b->id]]
        );

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    public function test_primary_flag_moves_to_exactly_one_image(): void
    {
        $product = Product::factory()->create();
        $a = ProductImage::create(['product_id' => $product->id, 'path' => 'a.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $b = ProductImage::create(['product_id' => $product->id, 'path' => 'b.jpg', 'sort_order' => 2, 'is_primary' => false]);

        $this->actingAs($this->manager)
            ->put(route('admin.products.images.primary', ['product' => $product->id, 'image' => $b->id]));

        $this->assertFalse($a->fresh()->is_primary);
        $this->assertTrue($b->fresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_deleting_the_primary_image_promotes_the_next_one(): void
    {
        $product = Product::factory()->create();
        Storage::disk('public')->put('a.jpg', 'x');
        Storage::disk('public')->put('a-thumb.jpg', 'x');

        $a = ProductImage::create(['product_id' => $product->id, 'path' => 'a.jpg', 'thumbnail_path' => 'a-thumb.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $b = ProductImage::create(['product_id' => $product->id, 'path' => 'b.jpg', 'sort_order' => 2, 'is_primary' => false]);

        $this->actingAs($this->manager)
            ->delete(route('admin.products.images.destroy', ['product' => $product->id, 'image' => $a->id]));

        $this->assertDatabaseMissing('product_images', ['id' => $a->id]);
        $this->assertTrue($b->fresh()->is_primary);

        // The stored file and its thumbnail both go with the row.
        Storage::disk('public')->assertMissing('a.jpg');
        Storage::disk('public')->assertMissing('a-thumb.jpg');
    }

    public function test_an_image_belonging_to_another_product_is_not_reachable(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $image = ProductImage::create(['product_id' => $other->id, 'path' => 'x.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->actingAs($this->manager)
            ->delete(route('admin.products.images.destroy', ['product' => $product->id, 'image' => $image->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }
}
