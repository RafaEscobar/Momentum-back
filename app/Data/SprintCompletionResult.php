<?php

namespace App\Data;

use App\Models\Sprint;

final readonly class SprintCompletionResult
{
    /**
     * @param  array{
     *     planned_points: int,
     *     completed_points: int,
     *     completed_tasks: int,
     *     unfinished_tasks: int
     * }  $summary
     * @param  list<int>  $completedTaskIds
     * @param  list<int>  $movedTaskIds
     */
    public function __construct(
        public Sprint $sprint,
        public array $summary,
        public array $completedTaskIds,
        public array $movedTaskIds,
    ) {}
}
