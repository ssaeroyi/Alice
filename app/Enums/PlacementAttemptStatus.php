<?php

namespace App\Enums;

enum PlacementAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
