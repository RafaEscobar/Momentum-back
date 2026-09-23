<?php

namespace App\Services;

use App\Data\SprintCompletionResult;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Enums\UnfinishedTaskAction;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SprintService
{
    public function __construct(private readonly ActivityService $activityService) {}

    public function activate(Sprint $sprint): Sprint
    {
        return DB::transaction(function () use ($sprint): Sprint {
            $this->lockProjectAndEnsureNoActiveSprint($sprint);

            $wasActive = $sprint->status === SprintStatus::Active;
            $sprint->update(['status' => SprintStatus::Active]);
            $sprint->refresh();

            if (! $wasActive) {
                $this->activityService->sprintStarted($sprint);
            }

            return $sprint;
        }, attempts: 3);
    }

    public function start(Sprint $sprint): Sprint
    {
        return DB::transaction(function () use ($sprint): Sprint {
            $this->lockProjectAndEnsureNoActiveSprint($sprint);
            $sprint->refresh();

            if ($sprint->status !== SprintStatus::Planned) {
                throw ValidationException::withMessages([
                    'status' => ['Only a planned sprint can be started.'],
                ]);
            }

            $sprint->update([
                'status' => SprintStatus::Active,
                'start_date' => $sprint->start_date ?? today(),
            ]);
            $sprint->refresh();
            $this->activityService->sprintStarted($sprint);

            return $sprint;
        }, attempts: 3);
    }

    public function complete(
        Sprint $sprint,
        UnfinishedTaskAction $unfinishedAction,
        ?int $nextSprintId = null,
    ): SprintCompletionResult {
        return DB::transaction(function () use ($sprint, $unfinishedAction, $nextSprintId): SprintCompletionResult {
            $sprint = Sprint::query()->whereKey($sprint->getKey())->lockForUpdate()->firstOrFail();

            if ($sprint->status !== SprintStatus::Active) {
                throw ValidationException::withMessages([
                    'status' => ['Only an active sprint can be completed.'],
                ]);
            }

            $nextSprint = $this->resolveNextSprint(
                sprint: $sprint,
                unfinishedAction: $unfinishedAction,
                nextSprintId: $nextSprintId,
            );

            $sprintTasks = Task::query()
                ->where('sprint_id', $sprint->getKey())
                ->select(['id', 'status'])
                ->lockForUpdate()
                ->get();

            $completedTaskIds = $sprintTasks
                ->where('status', TaskStatus::Done)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
            $movedTaskIds = $sprintTasks
                ->where('status', '<>', TaskStatus::Done)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $summary = $this->completionSummary($sprint);
            $unfinishedTasks = Task::query()
                ->where('sprint_id', $sprint->getKey())
                ->where('status', '<>', TaskStatus::Done);

            if ($unfinishedAction === UnfinishedTaskAction::Backlog) {
                $unfinishedTasks->update([
                    'sprint_id' => null,
                    'status' => TaskStatus::Backlog,
                    'completed_at' => null,
                ]);
            } else {
                $unfinishedTasks->update(['sprint_id' => $nextSprint?->getKey()]);
            }

            $sprint->update([
                'status' => SprintStatus::Completed,
                'completed_at' => now(),
            ]);
            $sprint->refresh();

            $this->activityService->sprintCompleted($sprint, [
                ...$summary,
                'unfinished_action' => $unfinishedAction->value,
                'next_sprint_id' => $nextSprint?->getKey(),
            ]);

            return new SprintCompletionResult(
                sprint: $sprint,
                summary: $summary,
                completedTaskIds: $completedTaskIds,
                movedTaskIds: $movedTaskIds,
            );
        }, attempts: 3);
    }

    private function resolveNextSprint(
        Sprint $sprint,
        UnfinishedTaskAction $unfinishedAction,
        ?int $nextSprintId,
    ): ?Sprint {
        if ($unfinishedAction === UnfinishedTaskAction::Backlog) {
            return null;
        }

        $nextSprint = $nextSprintId === null
            ? null
            : Sprint::query()->whereKey($nextSprintId)->lockForUpdate()->first();

        if ($nextSprint === null
            || $nextSprint->project_id !== $sprint->project_id
            || $nextSprint->is($sprint)
            || $nextSprint->status === SprintStatus::Completed) {
            throw ValidationException::withMessages([
                'next_sprint_id' => ['The selected next sprint is invalid.'],
            ]);
        }

        return $nextSprint;
    }

    /**
     * @return array{
     *     planned_points: int,
     *     completed_points: int,
     *     completed_tasks: int,
     *     unfinished_tasks: int
     * }
     */
    private function completionSummary(Sprint $sprint): array
    {
        $summary = Task::query()
            ->where('sprint_id', $sprint->getKey())
            ->selectRaw('COALESCE(SUM(story_points), 0) as planned_points')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN story_points ELSE 0 END), 0) as completed_points',
                [TaskStatus::Done->value],
            )
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_tasks', [TaskStatus::Done->value])
            ->selectRaw('SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END) as unfinished_tasks', [TaskStatus::Done->value])
            ->firstOrFail();

        return [
            'planned_points' => (int) $summary->getAttribute('planned_points'),
            'completed_points' => (int) $summary->getAttribute('completed_points'),
            'completed_tasks' => (int) $summary->getAttribute('completed_tasks'),
            'unfinished_tasks' => (int) $summary->getAttribute('unfinished_tasks'),
        ];
    }

    private function lockProjectAndEnsureNoActiveSprint(Sprint $sprint): void
    {
        Project::query()->whereKey($sprint->project_id)->lockForUpdate()->firstOrFail();

        $hasAnotherActiveSprint = Sprint::query()
            ->where('project_id', $sprint->project_id)
            ->where('status', SprintStatus::Active)
            ->whereKeyNot($sprint->getKey())
            ->exists();

        if ($hasAnotherActiveSprint) {
            throw ValidationException::withMessages([
                'status' => ['There is already an active sprint for this project.'],
            ]);
        }
    }
}
