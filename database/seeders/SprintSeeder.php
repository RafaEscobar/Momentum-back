<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Seeder;

class SprintSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::factory()
            ->has(Sprint::factory()->count(3))
            ->create();
    }
}
