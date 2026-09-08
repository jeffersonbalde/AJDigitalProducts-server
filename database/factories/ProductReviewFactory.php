<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductReview>
 */
class ProductReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'title' => $this->faker->optional()->sentence(4),
            'content' => $this->faker->paragraphs(2, true),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'status' => 'approved',
            'is_active' => true,
        ];
    }
}
