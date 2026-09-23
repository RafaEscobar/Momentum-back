<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'sprint_id' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'type' => TaskType::Task,
            'priority' => ProjectPriority::Medium,
            'status' => TaskStatus::Backlog,
            'story_points' => fake()->randomElement([1, 2, 3, 5, 8, 13]),
            'position' => 0,
            'completed_at' => null,
        ];
    }
}
