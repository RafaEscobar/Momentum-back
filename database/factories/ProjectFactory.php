<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'priority' => fake()->randomElement(ProjectPriority::cases()),
            'color' => fake()->hexColor(),
            'icon' => fake()->optional()->randomElement(['briefcase', 'code', 'rocket']),
            'start_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'target_date' => fn (array $attributes): string => CarbonImmutable::parse($attributes['start_date'])
                ->addMonths(3)
                ->toDateString(),
        ];
    }
}
