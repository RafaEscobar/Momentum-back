<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Activity::factory()->count(10)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);
    }
}
