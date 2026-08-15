<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Blueprints keyed by category slug. `variants` names the attribute codes
     * whose values get combined into the SKU matrix.
     */
    private const BLUEPRINTS = [
        [
            'title' => 'ROG Strix G16 Gaming Laptop',
            'brand' => 'asus',
            'category' => 'gaming-laptops',
            'price' => 1499.00,
            'compare_at' => 1699.00,
            'warranty' => 24,
            'year' => 2025,
            'weight' => 2.5,
            'variants' => ['ram' => ['16 GB', '32 GB'], 'storage' => ['512 GB', '1 TB']],
            'specs' => [
                'Processor' => ['CPU' => 'Intel Core i7-13650HX', 'Cores' => '14 cores / 20 threads'],
                'Graphics' => ['GPU' => 'NVIDIA GeForce RTX 4060 8GB'],
                'Display' => ['Screen Size' => '16 inch', 'Resolution' => '2560 x 1600', 'Refresh Rate' => '240 Hz'],
                'Battery' => ['Capacity' => '90 Wh'],
            ],
            'tags' => ['gaming', 'best-seller'],
        ],
        [
            'title' => 'MacBook Air 13 M3',
            'brand' => 'apple',
            'category' => 'ultrabooks',
            'price' => 1099.00,
            'warranty' => 12,
            'year' => 2025,
            'weight' => 1.24,
            'variants' => ['ram' => ['8 GB', '16 GB'], 'storage' => ['256 GB', '512 GB'], 'colour' => ['Space Grey', 'Silver']],
            'specs' => [
                'Processor' => ['CPU' => 'Apple M3', 'Cores' => '8-core CPU, 10-core GPU'],
                'Display' => ['Screen Size' => '13.6 inch', 'Resolution' => '2560 x 1664', 'Technology' => 'Liquid Retina'],
                'Battery' => ['Life' => 'Up to 18 hours'],
            ],
            'tags' => ['new-arrival', 'student-pick'],
        ],
        [
            'title' => 'ThinkPad X1 Carbon Gen 12',
            'brand' => 'lenovo',
            'category' => 'business-laptops',
            'price' => 1749.00,
            'warranty' => 36,
            'year' => 2025,
            'weight' => 1.12,
            'variants' => ['ram' => ['16 GB', '32 GB'], 'storage' => ['512 GB', '1 TB']],
            'specs' => [
                'Processor' => ['CPU' => 'Intel Core Ultra 7 155U'],
                'Display' => ['Screen Size' => '14 inch', 'Resolution' => '1920 x 1200'],
                'Security' => ['Fingerprint Reader' => 'Yes', 'TPM' => 'TPM 2.0'],
            ],
            'tags' => ['business'],
        ],
        [
            'title' => 'Samsung 990 PRO NVMe SSD',
            'brand' => 'samsung',
            'category' => 'nvme',
            'price' => 109.00,
            'compare_at' => 139.00,
            'warranty' => 60,
            'year' => 2024,
            'weight' => 0.01,
            'variants' => ['storage' => ['1 TB', '2 TB', '4 TB']],
            'specs' => [
                'Performance' => ['Read Speed' => '7450 MB/s', 'Write Speed' => '6900 MB/s'],
                'Form Factor' => ['Interface' => 'PCIe 4.0 x4', 'Format' => 'M.2 2280'],
                'Endurance' => ['TBW' => '1200 TBW'],
            ],
            'tags' => ['on-sale', 'best-seller'],
        ],
        [
            'title' => 'Kingston FURY Beast DDR5 Memory',
            'brand' => 'kingston',
            'category' => 'memory',
            'price' => 79.00,
            'warranty' => 24,
            'year' => 2024,
            'weight' => 0.05,
            'variants' => ['ram' => ['16 GB', '32 GB', '64 GB']],
            'specs' => [
                'Performance' => ['Speed' => 'DDR5-6000', 'Latency' => 'CL36'],
                'Compatibility' => ['Voltage' => '1.35 V', 'Form Factor' => 'DIMM'],
            ],
            'tags' => [],
        ],
        [
            'title' => 'MX Master 3S Wireless Mouse',
            'brand' => 'logitech',
            'category' => 'mice',
            'price' => 99.00,
            'warranty' => 12,
            'year' => 2024,
            'weight' => 0.14,
            'variants' => ['colour' => ['Black', 'Silver']],
            'specs' => [
                'Sensor' => ['DPI' => '8000 DPI', 'Tracking' => 'Darkfield'],
                'Connectivity' => ['Wireless' => 'Bluetooth + Logi Bolt'],
                'Battery' => ['Life' => 'Up to 70 days'],
            ],
            'tags' => ['best-seller'],
        ],
        [
            'title' => 'K70 RGB PRO Mechanical Keyboard',
            'brand' => 'corsair',
            'category' => 'keyboards',
            'price' => 169.00,
            'warranty' => 24,
            'year' => 2024,
            'weight' => 1.2,
            'variants' => ['switch_type' => ['Red Linear', 'Brown Tactile', 'Silent Red']],
            'specs' => [
                'Build' => ['Frame' => 'Brushed aluminium', 'Keycaps' => 'PBT double-shot'],
                'Features' => ['Polling Rate' => '8000 Hz', 'Backlight' => 'Per-key RGB'],
            ],
            'tags' => ['gaming'],
        ],
        [
            'title' => 'Odyssey G7 Curved Gaming Monitor',
            'brand' => 'samsung',
            'category' => 'monitors',
            'price' => 649.00,
            'compare_at' => 749.00,
            'warranty' => 24,
            'year' => 2025,
            'weight' => 7.8,
            'variants' => ['screen_size' => ['27 inch', '32 inch']],
            'specs' => [
                'Display' => ['Resolution' => '2560 x 1440', 'Refresh Rate' => '240 Hz', 'Panel' => 'VA curved 1000R'],
                'Response' => ['Response Time' => '1 ms GtG'],
                'Ports' => ['Inputs' => '2x HDMI 2.1, 1x DisplayPort 1.4'],
            ],
            'tags' => ['gaming', 'on-sale'],
        ],
        [
            'title' => 'G-SHOCK GA-2100 Watch',
            'brand' => 'casio',
            'category' => 'digital-watches',
            'price' => 99.00,
            'warranty' => 24,
            'year' => 2024,
            'weight' => 0.051,
            'variants' => [
                'case_size' => ['45 mm'],
                'strap_material' => ['Silicone', 'Stainless Steel'],
                'colour' => ['Black', 'Blue'],
            ],
            'specs' => [
                'Movement' => ['Type' => 'Quartz digital-analog'],
                'Durability' => ['Water Resistance' => '200 m', 'Shock Resistant' => 'Yes'],
                'Battery' => ['Life' => 'Approx. 3 years'],
            ],
            'tags' => ['best-seller'],
        ],
        [
            'title' => 'Garmin Venu 3 Smartwatch',
            'brand' => 'garmin',
            'category' => 'smartwatches',
            'price' => 449.00,
            'warranty' => 12,
            'year' => 2025,
            'weight' => 0.047,
            'variants' => [
                'case_size' => ['41 mm', '45 mm'],
                'strap_material' => ['Silicone', 'Leather'],
            ],
            'specs' => [
                'Display' => ['Type' => 'AMOLED', 'Size' => '1.4 inch'],
                'Health' => ['Sensors' => 'HR, SpO2, ECG', 'Sleep Coach' => 'Yes'],
                'Battery' => ['Smartwatch Mode' => 'Up to 14 days'],
                'Connectivity' => ['GPS' => 'Multi-band GNSS'],
            ],
            'tags' => ['new-arrival'],
        ],
        [
            'title' => 'WH-1000XM5 Noise Cancelling Headphones',
            'brand' => 'samsung',
            'category' => 'headphones',
            'price' => 379.00,
            'warranty' => 12,
            'year' => 2024,
            'weight' => 0.25,
            'variants' => ['colour' => ['Black', 'Silver']],
            'specs' => [
                'Audio' => ['Driver' => '30 mm', 'Codecs' => 'LDAC, AAC, SBC'],
                'Features' => ['Noise Cancelling' => 'Adaptive ANC'],
                'Battery' => ['Life' => 'Up to 30 hours'],
            ],
            'tags' => ['best-seller'],
        ],
        [
            'title' => 'PowerCore 26800 Power Bank',
            'brand' => 'anker',
            'category' => 'cables-adapters',
            'price' => 69.00,
            'warranty' => 18,
            'year' => 2024,
            'weight' => 0.49,
            'variants' => ['colour' => ['Black']],
            'specs' => [
                'Capacity' => ['Battery' => '26800 mAh'],
                'Output' => ['Ports' => '3x USB-A', 'Max Output' => '6 A total'],
            ],
            'tags' => [],
        ],
    ];

    public function run(): void
    {
        $brands = Brand::pluck('id', 'slug');
        $categories = Category::pluck('id', 'slug');
        $tags = Tag::pluck('id', 'slug');
        $attributes = Attribute::with('values')->get()->keyBy('code');

        foreach (self::BLUEPRINTS as $index => $blueprint) {
            $product = $this->createProduct($blueprint, $brands, $categories, $index);

            $this->createVariants($product, $blueprint, $attributes);
            $this->createSpecifications($product, $blueprint['specs']);
            $this->createImages($product);

            $tagIds = collect($blueprint['tags'])->map(fn ($slug) => $tags[$slug] ?? null)->filter();
            $product->tags()->sync($tagIds);

            $product->syncStockFromVariants();
        }
    }

    private function createProduct(
        array $blueprint,
        $brands,
        $categories,
        int $index
    ): Product {
        $slug = str($blueprint['title'])->slug()->value();

        return Product::updateOrCreate(['slug' => $slug], [
            'title' => ['en' => $blueprint['title'], 'km' => $blueprint['title']],
            'sku' => 'SKU-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'short_description' => [
                'en' => Str::limit($blueprint['title'].' — in stock now with local warranty.', 120),
            ],
            'description' => [
                'en' => '<p>'.$blueprint['title'].' is available with official local warranty '
                    .'and same-day delivery in Phnom Penh.</p>',
            ],
            'brand_id' => $brands[$blueprint['brand']] ?? null,
            'category_id' => $categories[$blueprint['category']] ?? null,
            'price' => $blueprint['price'],
            'compare_at_price' => $blueprint['compare_at'] ?? null,
            'cost_price' => round($blueprint['price'] * 0.72, 2),
            'status' => ProductStatus::Published,
            'condition' => 'new',
            'is_featured' => $index < 4,
            'warranty_months' => $blueprint['warranty'],
            'release_year' => $blueprint['year'],
            'weight' => $blueprint['weight'],
            'meta_title' => ['en' => $blueprint['title']],
        ]);
    }

    /** Builds the cartesian product of the blueprint's attribute values. */
    private function createVariants(Product $product, array $blueprint, $attributes): void
    {
        $axes = [];

        foreach ($blueprint['variants'] as $code => $labels) {
            $attribute = $attributes[$code] ?? null;

            if (! $attribute) {
                continue;
            }

            $axes[$code] = collect($labels)
                ->map(fn ($label) => $attribute->values
                    ->first(fn (AttributeValue $v) => $v->getTranslation('label', 'en') === $label))
                ->filter()
                ->values()
                ->all();
        }

        $combinations = $this->cartesian(array_filter($axes));
        $counter = 0;

        foreach ($combinations as $combination) {
            $counter++;
            $label = collect($combination)
                ->map(fn (AttributeValue $v) => $v->getTranslation('label', 'en'))
                ->implode(' / ');

            $variant = ProductVariant::updateOrCreate(
                ['sku' => $product->sku.'-V'.str_pad((string) $counter, 2, '0', STR_PAD_LEFT)],
                [
                    'product_id' => $product->id,
                    'label' => $label ?: null,
                    'price' => round($product->price + ($counter - 1) * 60, 2),
                    'compare_at_price' => $product->compare_at_price
                        ? round($product->compare_at_price + ($counter - 1) * 60, 2)
                        : null,
                    'cost_price' => round(($product->price + ($counter - 1) * 60) * 0.72, 2),
                    'stock_quantity' => random_int(0, 40),
                    'low_stock_threshold' => 5,
                    'weight' => $product->weight,
                    'is_default' => $counter === 1,
                    'is_active' => true,
                ]
            );

            $pivot = [];

            foreach ($combination as $value) {
                $pivot[$value->id] = ['attribute_id' => $value->attribute_id];
            }

            $variant->attributeValues()->sync($pivot);
        }
    }

    private function cartesian(array $axes): array
    {
        $result = [[]];

        foreach ($axes as $values) {
            $append = [];

            foreach ($result as $partial) {
                foreach ($values as $value) {
                    $append[] = [...$partial, $value];
                }
            }

            $result = $append;
        }

        return $result;
    }

    private function createSpecifications(Product $product, array $specs): void
    {
        $order = 0;

        foreach ($specs as $group => $rows) {
            foreach ($rows as $key => $value) {
                ProductSpecification::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'sort_order' => ++$order,
                    ],
                    [
                        'group' => ['en' => $group],
                        'key' => ['en' => $key],
                        'value' => ['en' => $value],
                    ]
                );
            }
        }
    }

    private function createImages(Product $product): void
    {
        // Placeholder paths — Phase 3 replaces these with real uploads.
        foreach (range(1, 4) as $i) {
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'sort_order' => $i],
                [
                    'path' => "https://placehold.co/800x800/1f2937/ffffff?text={$product->id}-{$i}",
                    'alt_text' => $product->getTranslation('title', 'en'),
                    'is_primary' => $i === 1,
                ]
            );
        }
    }
}
