<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TaskStatisticsService
{
    /**
     * @return array{
     *     total: int,
     *     backlog: int,
     *     todo: int,
     *     in_progress: int,
     *     blocked: int,
     *     done: int,
     *     pending: int,
     *     total_story_points: int,
     *     completed_story_points: int,
     *     progress: int
     * }
     */
    public function forProject(Project $project): array
    {
        return $this->aggregate(Task::query()->where('project_id', $project->getKey()));
    }

    /**
     * @return array{
     *     total: int,
     *     backlog: int,
     *     todo: int,
     *     in_progress: int,
     *     blocked: int,
     *     done: int,
     *     pending: int,
     *     total_story_points: int,
     *     completed_story_points: int,
     *     progress: int
     * }
     */
    public function forUser(User $user): array
    {
        return $this->aggregate(
            Task::query()->whereHas(
                'project',
                fn (Builder $query) => $query->where('user_id', $user->getKey()),
            ),
        );
    }

    /**
     * @param  Builder<Task>  $query
     * @return array{
     *     total: int,
     *     backlog: int,
     *     todo: int,
     *     in_progress: int,
     *     blocked: int,
     *     done: int,
     *     pending: int,
     *     total_story_points: int,
     *     completed_story_points: int,
     *     progress: int
     * }
     */
    private function aggregate(Builder $query): array
    {
        $statistics = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as backlog', [TaskStatus::Backlog->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as todo', [TaskStatus::Todo->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', [TaskStatus::InProgress->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as blocked', [TaskStatus::Blocked->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [TaskStatus::Done->value])
            ->selectRaw('COALESCE(SUM(story_points), 0) as total_story_points')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN story_points ELSE 0 END), 0) as completed_story_points',
                [TaskStatus::Done->value],
            )
            ->firstOrFail();

        $backlog = (int) $statistics->getAttribute('backlog');
        $todo = (int) $statistics->getAttribute('todo');
        $inProgress = (int) $statistics->getAttribute('in_progress');
        $blocked = (int) $statistics->getAttribute('blocked');
        $done = (int) $statistics->getAttribute('done');
        $totalStoryPoints = (int) $statistics->getAttribute('total_story_points');
        $completedStoryPoints = (int) $statistics->getAttribute('completed_story_points');

        return [
            'total' => (int) $statistics->getAttribute('total'),
            'backlog' => $backlog,
            'todo' => $todo,
            'in_progress' => $inProgress,
            'blocked' => $blocked,
            'done' => $done,
            'pending' => $backlog + $todo + $blocked,
            'total_story_points' => $totalStoryPoints,
            'completed_story_points' => $completedStoryPoints,
            'progress' => Project::calculateProgress($completedStoryPoints, $totalStoryPoints),
        ];
    }
}
