<?php

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;

test('domain enum exposes its expected string values', function (string $enum, array $expectedValues) {
    $values = array_map(
        fn (BackedEnum $case): string => $case->value,
        $enum::cases(),
    );

    expect($values)->toBe($expectedValues);
})->with([
    'project status' => [ProjectStatus::class, ['active', 'paused', 'completed', 'archived']],
    'project priority' => [ProjectPriority::class, ['low', 'medium', 'high', 'critical']],
    'task type' => [TaskType::class, ['story', 'task', 'bug', 'improvement']],
    'task status' => [TaskStatus::class, ['backlog', 'todo', 'in_progress', 'blocked', 'done']],
    'sprint status' => [SprintStatus::class, ['planned', 'active', 'completed']],
]);
