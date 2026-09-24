<?php

namespace App\Enums;

enum TaskType: string
{
    case Story = 'story';
    case Task = 'task';
    case Bug = 'bug';
    case Improvement = 'improvement';
}
