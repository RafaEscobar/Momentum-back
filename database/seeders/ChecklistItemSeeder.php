<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\Task;
use Illuminate\Database\Seeder;

class ChecklistItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Task::factory()
            ->has(ChecklistItem::factory()->count(5), 'checklistItems')
            ->create();
    }
}
