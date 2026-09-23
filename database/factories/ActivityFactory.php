<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
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
            'project_id' => fn (array $attributes) => Project::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->getKey(),
            'subject_type' => null,
            'subject_id' => null,
            'type' => 'task_created',
            'description' => fake()->sentence(),
            'metadata' => null,
        ];
    }
}
