<?php

declare(strict_types=1);

namespace App\Enum;

enum DeploymentUser: string
{
    case NOTIFY = 'notify';
    case CONFIRM = 'confirm';
}
