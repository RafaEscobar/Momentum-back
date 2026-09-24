<?php

namespace Database\Factories;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->bothify('Sprint ###??'),
            'goal' => fake()->optional()->sentence(),
            'start_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'end_date' => fn (array $attributes): string => CarbonImmutable::parse($attributes['start_date'])
                ->addWeeks(2)
                ->toDateString(),
            'status' => SprintStatus::Planned,
            'completed_at' => null,
        ];
    }
}
