<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 *
 * Attributes GENERATE variants (RAM, Storage, Colour). Descriptive-only data
 * belongs in ProductSpecification instead.
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        $code = fake()->unique()->lexify('attr_????');

        return [
            'name' => ['en' => ucfirst(str_replace('_', ' ', $code)), 'km' => $code],
            'code' => $code,
            'input_type' => 'select',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function code(string $code, ?string $name = null): static
    {
        return $this->state(fn () => [
            'code' => $code,
            'name' => ['en' => $name ?? ucfirst($code), 'km' => $name ?? $code],
        ]);
    }

    public function colour(): static
    {
        return $this->state(fn () => ['input_type' => 'colour']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
