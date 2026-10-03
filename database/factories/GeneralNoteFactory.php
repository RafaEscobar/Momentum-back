<?php

namespace Database\Factories;

use App\Models\GeneralNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneralNote>
 */
class GeneralNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'content' => '## '.fake()->sentence()."\n\n".fake()->paragraph(),
        ];
    }
}
