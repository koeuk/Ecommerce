<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InnoDB does not update a FULLTEXT index until the transaction commits, so
 * rows written inside RefreshDatabase's wrapping transaction are invisible
 * to MATCH ... AGAINST. Emptying $connectionsToTransact drops that wrapper
 * — writes commit for real — at the cost of having to clear the table
 * ourselves, since nothing rolls back afterwards.
 */
class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string> */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Writes persist between tests here, so start each one from empty.
        $this->clearCatalog();
    }

    /** Nothing rolls back, so leftovers would leak into the next test class. */
    protected function tearDown(): void
    {
        $this->clearCatalog();

        parent::tearDown();
    }

    private function clearCatalog(): void
    {
        Product::withTrashed()->forceDelete();
        Brand::withTrashed()->forceDelete();
        Category::withTrashed()->forceDelete();
    }

    private function product(string $title, string $sku): Product
    {
        return Product::factory()->create([
            'title' => ['en' => $title, 'km' => $title],
            'sku' => $sku,
        ]);
    }

    public function test_it_matches_on_the_title(): void
    {
        $match = $this->product('Asus Gaming Laptop', 'LAP-001');
        $this->product('Casio Digital Watch', 'WAT-001');

        $results = Product::search('Gaming')->pluck('id');

        $this->assertTrue($results->contains($match->id));
        $this->assertCount(1, $results);
    }

    public function test_it_matches_on_a_prefix(): void
    {
        $match = $this->product('Asus Gaming Laptop', 'LAP-001');

        // Trailing wildcard — "lapt" should still find "Laptop".
        $this->assertTrue(Product::search('lapt')->pluck('id')->contains($match->id));
    }

    public function test_a_hyphenated_sku_is_not_read_as_an_exclusion(): void
    {
        $match = $this->product('Asus Gaming Laptop', 'LAP-001');
        $this->product('Casio Digital Watch', 'WAT-001');

        // In boolean mode a raw "-001" would mean "exclude 001", which would
        // drop the very row being searched for.
        $results = Product::search('LAP-001')->pluck('id');

        $this->assertTrue($results->contains($match->id));
    }

    public function test_all_terms_must_match(): void
    {
        $this->product('Asus Gaming Laptop', 'LAP-001');
        $this->product('Casio Gaming Watch', 'WAT-001');

        // Each token is required, so this narrows rather than widens.
        $this->assertCount(1, Product::search('Gaming Laptop')->get());
        $this->assertCount(2, Product::search('Gaming')->get());
    }

    public function test_a_short_term_falls_back_to_like(): void
    {
        $match = $this->product('Asus ROG Laptop', 'LAP-001');

        // Below MySQL's minimum token length, so FULLTEXT would return nothing.
        $this->assertTrue(Product::search('AS')->pluck('id')->contains($match->id));
    }

    public function test_a_blank_term_does_not_filter(): void
    {
        $this->product('Asus Gaming Laptop', 'LAP-001');
        $this->product('Casio Digital Watch', 'WAT-001');

        $this->assertCount(2, Product::search('')->get());
        $this->assertCount(2, Product::search('   ')->get());
        $this->assertCount(2, Product::search(null)->get());
    }

    public function test_it_searches_the_khmer_title_too(): void
    {
        $match = Product::factory()->create([
            'title' => ['en' => 'Gaming Laptop', 'km' => 'កុំព្យូទ័រហ្គេម'],
            'sku' => 'LAP-002',
        ]);

        // Khmer has no spaces, so this exercises the LIKE fallback path.
        $this->assertTrue(Product::search('កុំព្យូទ័រហ្គេម')->pluck('id')->contains($match->id));
    }

    public function test_search_composes_with_other_scopes(): void
    {
        $published = $this->product('Asus Gaming Laptop', 'LAP-001');
        Product::factory()->draft()->create([
            'title' => ['en' => 'Asus Gaming Desktop'],
            'sku' => 'DSK-001',
        ]);

        $results = Product::published()->search('Asus')->pluck('id');

        $this->assertSame([$published->id], $results->all());
    }

    public function test_the_generated_column_tracks_title_edits(): void
    {
        $product = $this->product('Original Title', 'SKU-100');

        $product->update(['title' => ['en' => 'Replacement Heading', 'km' => 'Replacement Heading']]);

        $this->assertCount(0, Product::search('Original')->get());
        $this->assertCount(1, Product::search('Replacement')->get());
    }

    public function test_editing_one_locale_leaves_the_other_searchable(): void
    {
        $product = $this->product('Original Title', 'SKU-100');

        // spatie merges translations rather than replacing them, so writing
        // only `en` leaves the Khmer value — and its index entry — in place.
        $product->update(['title' => ['en' => 'Replacement Heading']]);

        $this->assertSame('Original Title', $product->fresh()->getTranslation('title', 'km'));
        $this->assertCount(1, Product::search('Original')->get());
        $this->assertCount(1, Product::search('Replacement')->get());
    }
}
