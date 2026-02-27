<?php

declare(strict_types=1);

namespace App\Enum;

enum DeploymentConfirmation: string
{
    case JOIN = 'join';
    case DEPLOYMENT = 'deployment';
    case COMPLETION = 'completion';
}
