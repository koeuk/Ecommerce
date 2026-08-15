<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Brands, the category tree, attributes and tags — the structure products
 * hang off. See Appendix B of docs/ROADMAP.md for the taxonomy.
 */
class CatalogStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->brands();
        $this->categories();
        $this->attributes();
        $this->tags();
    }

    private function brands(): void
    {
        $brands = [
            'Asus', 'Dell', 'HP', 'Lenovo', 'Apple', 'Acer', 'MSI',
            'Samsung', 'LG', 'Logitech', 'Razer', 'Corsair', 'Kingston',
            'Western Digital', 'Seagate', 'Casio', 'Seiko', 'Citizen', 'Garmin', 'Anker',
        ];

        foreach ($brands as $i => $name) {
            Brand::updateOrCreate(
                ['slug' => str($name)->slug()->value()],
                [
                    'name' => ['en' => $name, 'km' => $name],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    private function categories(): void
    {
        $tree = [
            'Computers' => [
                'Laptops' => ['Gaming Laptops', 'Business Laptops', 'Ultrabooks', '2-in-1 Laptops'],
                'Desktops' => ['Gaming PCs', 'All-in-One', 'Mini PCs', 'Workstations'],
                'Tablets' => [],
            ],
            'Computer Components' => [
                'Processors' => [], 'Graphics Cards' => [], 'Memory' => [],
                'Storage' => ['SSD', 'HDD', 'NVMe'],
                'Motherboards' => [], 'Power Supplies' => [], 'Cases' => [], 'Cooling' => [],
            ],
            'Accessories' => [
                'Keyboards' => [], 'Mice' => [], 'Monitors' => [], 'Headsets' => [],
                'Webcams' => [], 'Docking Stations' => [], 'Cables & Adapters' => [],
                'Laptop Bags' => [],
            ],
            'Watches' => [
                'Smartwatches' => [], 'Analog Watches' => [], 'Digital Watches' => [],
                'Luxury Watches' => [], 'Watch Accessories' => [],
            ],
            'Audio' => ['Headphones' => [], 'Earbuds' => [], 'Speakers' => []],
            'Networking' => ['Routers' => [], 'Switches' => [], 'WiFi Adapters' => []],
            'Printers & Scanners' => [],
            'Gaming' => ['Consoles' => [], 'Controllers' => []],
        ];

        $order = 0;

        foreach ($tree as $rootName => $children) {
            $root = $this->category($rootName, null, ++$order, isFeatured: true);

            foreach ($children as $childName => $grandChildren) {
                $child = $this->category($childName, $root->id, ++$order);

                foreach ($grandChildren as $grandChildName) {
                    $this->category($grandChildName, $child->id, ++$order);
                }
            }
        }
    }

    private function category(
        string $name,
        ?int $parentId,
        int $order,
        bool $isFeatured = false
    ): Category {
        return Category::updateOrCreate(
            ['slug' => str($name)->slug()->value()],
            [
                'parent_id' => $parentId,
                'name' => ['en' => $name, 'km' => $name],
                'is_active' => true,
                'is_featured' => $isFeatured,
                'sort_order' => $order,
            ]
        );
    }

    private function attributes(): void
    {
        $attributes = [
            'ram' => ['RAM', 'select', ['4 GB', '8 GB', '16 GB', '32 GB', '64 GB']],
            'storage' => ['Storage', 'select', ['256 GB', '512 GB', '1 TB', '2 TB', '4 TB']],
            'colour' => ['Colour', 'colour', [
                'Black' => '#000000', 'Silver' => '#C0C0C0', 'Space Grey' => '#4A4A4A',
                'White' => '#FFFFFF', 'Blue' => '#1E40AF', 'Rose Gold' => '#B76E79',
            ]],
            'case_size' => ['Case Size', 'button', ['38 mm', '41 mm', '42 mm', '45 mm', '46 mm']],
            'strap_material' => ['Strap Material', 'select', [
                'Stainless Steel', 'Leather', 'Silicone', 'Nylon', 'Titanium',
            ]],
            'screen_size' => ['Screen Size', 'select', [
                '13.3 inch', '14 inch', '15.6 inch', '16 inch', '17.3 inch',
            ]],
            'switch_type' => ['Switch Type', 'select', [
                'Red Linear', 'Blue Clicky', 'Brown Tactile', 'Silent Red',
            ]],
        ];

        $order = 0;

        foreach ($attributes as $code => [$name, $inputType, $values]) {
            $attribute = Attribute::updateOrCreate(['code' => $code], [
                'name' => ['en' => $name, 'km' => $name],
                'input_type' => $inputType,
                'is_active' => true,
                'sort_order' => ++$order,
            ]);

            $valueOrder = 0;

            foreach ($values as $key => $value) {
                $label = is_string($key) ? $key : $value;
                $hex = is_string($key) ? $value : null;

                AttributeValue::updateOrCreate(
                    [
                        'attribute_id' => $attribute->id,
                        'value' => str($label)->slug()->value(),
                    ],
                    [
                        'label' => ['en' => $label, 'km' => $label],
                        'colour_hex' => $hex,
                        'sort_order' => ++$valueOrder,
                    ]
                );
            }
        }
    }

    private function tags(): void
    {
        foreach (['New Arrival', 'Best Seller', 'On Sale', 'Gaming', 'Business', 'Student Pick'] as $name) {
            Tag::updateOrCreate(
                ['slug' => str($name)->slug()->value()],
                ['name' => ['en' => $name, 'km' => $name]]
            );
        }
    }
}
