<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'subtitle' => $this->faker->optional()->sentence(5),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'old_price' => null,
            'on_sale' => false,
            'category' => $this->faker->word(),
            'availability' => 'In Stock',
            'image_type' => null,
            'color' => null,
            'accent_color' => null,
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->optional()->paragraph(),
            'is_active' => true,
            'stock_quantity' => 0,
        ];
    }
}
