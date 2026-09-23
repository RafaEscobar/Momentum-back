<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ActivityService
{
    private const MAX_METADATA_ITEMS = 10;

    private const MAX_METADATA_BYTES = 2048;

    /**
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    public function log(
        Project $project,
        ?Model $subject,
        ActivityType $type,
        string $description,
        array $metadata = [],
    ): Activity {
        $this->ensureMetadataIsBounded($metadata);
        $this->ensureSubjectBelongsToProject($subject, $project);

        $activity = new Activity([
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        $activity->project()->associate($project);
        $activity->user()->associate($project->user_id);

        if ($subject !== null) {
            $activity->subject()->associate($subject);
        }

        $activity->save();

        return $activity;
    }

    public function taskCreated(Task $task): Activity
    {
        return $this->log(
            project: $task->project,
            subject: $task,
            type: ActivityType::TaskCreated,
            description: "Tarea creada: {$task->title}",
        );
    }

    /** @return list<Activity> */
    public function taskStatusChanged(Task $task, TaskStatus $previousStatus): array
    {
        if ($previousStatus === $task->status) {
            return [];
        }

        $activities = [
            $this->log(
                project: $task->project,
                subject: $task,
                type: ActivityType::TaskStatusChanged,
                description: "Estado de {$task->title}: {$previousStatus->value} -> {$task->status->value}",
                metadata: [
                    'from' => $previousStatus->value,
                    'to' => $task->status->value,
                ],
            ),
        ];

        if ($task->status === TaskStatus::Done) {
            $activities[] = $this->log(
                project: $task->project,
                subject: $task,
                type: ActivityType::TaskCompleted,
                description: "Tarea terminada: {$task->title}",
            );
        }

        return $activities;
    }

    public function sprintStarted(Sprint $sprint): Activity
    {
        return $this->log(
            project: $sprint->project,
            subject: $sprint,
            type: ActivityType::SprintStarted,
            description: "Sprint iniciado: {$sprint->name}",
        );
    }

    /** @param array<string, bool|float|int|string|null> $metadata */
    public function sprintCompleted(Sprint $sprint, array $metadata = []): Activity
    {
        return $this->log(
            project: $sprint->project,
            subject: $sprint,
            type: ActivityType::SprintCompleted,
            description: "Sprint terminado: {$sprint->name}",
            metadata: $metadata,
        );
    }

    /** @param array<string, bool|float|int|string|null> $metadata */
    private function ensureMetadataIsBounded(array $metadata): void
    {
        if (count($metadata) > self::MAX_METADATA_ITEMS) {
            throw new InvalidArgumentException('Activity metadata may contain at most 10 items.');
        }

        $encodedMetadata = json_encode($metadata, JSON_THROW_ON_ERROR);

        if (strlen($encodedMetadata) > self::MAX_METADATA_BYTES) {
            throw new InvalidArgumentException('Activity metadata may not exceed 2048 bytes.');
        }
    }

    private function ensureSubjectBelongsToProject(?Model $subject, Project $project): void
    {
        if ($subject === null) {
            return;
        }

        $belongsToProject = $subject instanceof Project
            ? $subject->is($project)
            : ! array_key_exists('project_id', $subject->getAttributes())
                || $subject->getAttribute('project_id') === $project->getKey();

        if (! $belongsToProject) {
            throw new InvalidArgumentException('The activity subject must belong to the project.');
        }
    }
}
