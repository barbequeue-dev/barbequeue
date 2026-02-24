<?php

declare(strict_types=1);

namespace App\Enum;

enum DeploymentStatus: string
{
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case AWAITING_DEPLOYMENT = 'awaiting_deployment';
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';
}
