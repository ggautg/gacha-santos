<?php

namespace Database\Factories;

use App\Models\GospelVerse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GospelVerse>
 */
class GospelVerseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'translation' => 'spa_r09',
            'book' => 'JHN',
            'chapter' => fake()->numberBetween(1, 21),
            'verse' => fake()->numberBetween(1, 30),
            'text' => fake()->sentence(),
        ];
    }
}
