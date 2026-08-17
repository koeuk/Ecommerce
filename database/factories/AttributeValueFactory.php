<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    public function definition(): array
    {
        // Unique per attribute — the table has a (attribute_id, value) unique key.
        $value = fake()->unique()->lexify('val_????');

        return [
            'attribute_id' => Attribute::factory(),
            'label' => ['en' => strtoupper($value), 'km' => $value],
            'value' => $value,
            'colour_hex' => null,
            'sort_order' => 0,
        ];
    }

    public function of(Attribute $attribute): static
    {
        return $this->state(fn () => ['attribute_id' => $attribute->id]);
    }

    public function label(string $label): static
    {
        return $this->state(fn () => [
            'label' => ['en' => $label, 'km' => $label],
            'value' => strtolower(str_replace(' ', '', $label)).'-'.fake()->unique()->numerify('###'),
        ]);
    }
}
