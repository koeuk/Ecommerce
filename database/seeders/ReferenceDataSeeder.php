<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->currencies();
        $this->taxRates();
        $this->shipping();
        $this->settings();
    }

    private function currencies(): void
    {
        Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Currency::updateOrCreate(['code' => 'KHR'], [
            'name' => 'Cambodian Riel',
            'symbol' => '៛',
            'exchange_rate' => 4100,
            'decimal_places' => 0,
            'is_base' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    private function taxRates(): void
    {
        TaxRate::updateOrCreate(['name' => 'VAT 10%'], [
            'rate' => 10.00,
            'is_inclusive' => false,
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::updateOrCreate(['name' => 'Zero rated'], [
            'rate' => 0.00,
            'is_inclusive' => false,
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    private function shipping(): void
    {
        $phnomPenh = ShippingZone::updateOrCreate(['name' => 'Phnom Penh'], [
            'provinces' => ['Phnom Penh'],
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $provinces = ShippingZone::updateOrCreate(['name' => 'Provinces'], [
            'provinces' => [
                'Banteay Meanchey', 'Battambang', 'Kampong Cham', 'Kampong Chhnang',
                'Kampong Speu', 'Kampong Thom', 'Kampot', 'Kandal', 'Kep', 'Koh Kong',
                'Kratie', 'Mondulkiri', 'Oddar Meanchey', 'Pailin', 'Preah Sihanouk',
                'Preah Vihear', 'Prey Veng', 'Pursat', 'Ratanakiri', 'Siem Reap',
                'Stung Treng', 'Svay Rieng', 'Takeo', 'Tbong Khmum',
            ],
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        ShippingMethod::updateOrCreate(
            ['shipping_zone_id' => $phnomPenh->id, 'rate_type' => 'flat'],
            [
                'name' => ['en' => 'Standard Delivery', 'km' => 'ការដឹកជញ្ជូនធម្មតា'],
                'description' => ['en' => 'Delivered within Phnom Penh in 1–2 days.'],
                'base_rate' => 1.50,
                'free_above_total' => 100.00,
                'min_days' => 1,
                'max_days' => 2,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['shipping_zone_id' => $provinces->id, 'rate_type' => 'weight'],
            [
                'name' => ['en' => 'Provincial Delivery', 'km' => 'ការដឹកជញ្ជូនទៅខេត្ត'],
                'description' => ['en' => 'Delivered to provincial addresses in 3–5 days.'],
                'base_rate' => 3.00,
                'per_kg_rate' => 0.50,
                'free_above_total' => 250.00,
                'min_days' => 3,
                'max_days' => 5,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function settings(): void
    {
        $settings = [
            ['general', 'store_name', ['en' => 'Boomer', 'km' => 'Boomer']],
            ['general', 'store_email', 'support@example.com'],
            ['general', 'store_phone', '+855 12 345 678'],
            ['general', 'store_address', ['en' => 'Phnom Penh, Cambodia']],
            ['general', 'default_currency', 'USD'],
            ['general', 'supported_locales', ['en', 'km']],
            ['tax', 'prices_include_tax', false],
            ['shipping', 'free_shipping_threshold', 100],
        ];

        foreach ($settings as [$group, $key, $value]) {
            Setting::updateOrCreate(['key' => $key], ['group' => $group, 'value' => $value]);
        }
    }
}
