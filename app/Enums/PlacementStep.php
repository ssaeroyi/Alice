<?php

namespace App\Enums;

enum PlacementStep: string
{
    case Vocab = 'vocab';
    case Cloze = 'cloze';
    case Comprehension = 'comprehension';
}
