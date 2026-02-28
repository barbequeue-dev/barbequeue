<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\DeploymentConfirmation;
use App\Entity\DeploymentUser;
use App\Enum\DeploymentConfirmation as ConfirmationType;

class DeploymentConfirmationFactory
{
    public function create(DeploymentUser $deploymentUser, ConfirmationType $type): DeploymentConfirmation
    {
        $confirmation = new DeploymentConfirmation()
            ->setType($type);

        $deploymentUser->addConfirmation($confirmation);

        return $confirmation;
    }
}
