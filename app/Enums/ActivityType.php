<?php

namespace App\Enums;

enum ActivityType: string
{
    case ProjectCreated = 'project_created';
    case ProjectUpdated = 'project_updated';
    case TaskCreated = 'task_created';
    case TaskStatusChanged = 'task_status_changed';
    case TaskCompleted = 'task_completed';
    case SprintCreated = 'sprint_created';
    case SprintStarted = 'sprint_started';
    case SprintCompleted = 'sprint_completed';
}
