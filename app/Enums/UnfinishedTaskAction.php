<?php

namespace App\Enums;

enum UnfinishedTaskAction: string
{
    case Backlog = 'backlog';
    case NextSprint = 'next_sprint';
}
