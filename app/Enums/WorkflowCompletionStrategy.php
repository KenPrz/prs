<?php

namespace App\Enums;

enum WorkflowCompletionStrategy: string
{
    case All = 'ALL';
    case Any = 'ANY';
}
