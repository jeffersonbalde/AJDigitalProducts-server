<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductFaq>
 */
class ProductFaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence(6, true),
            'answer' => $this->faker->paragraph(),
            'display_order' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(80),
        ];
    }
}
