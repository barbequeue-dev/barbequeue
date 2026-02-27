<?php

declare(strict_types=1);

namespace App\Enum;

enum DeploymentConfirmation: string
{
    case JOIN = 'join';
    case START = 'start';
    case COMPLETION = 'completion';
}
