<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Database\Seeder;

class ProjectNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::factory()->create();

        ProjectNote::factory()->for($project)->count(10)->create();
    }
}
