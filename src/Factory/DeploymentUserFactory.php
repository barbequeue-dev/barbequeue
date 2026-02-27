<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Deployment;
use App\Entity\DeploymentUser;
use App\Entity\User;
use App\Enum\DeploymentUser as DeploymentUserType;

class DeploymentUserFactory
{
    public function __construct(
        private DeploymentConfirmationFactory $deploymentConfirmationFactory,
    ) {
    }

    public function create(
        User $user,
        Deployment $deployment,
        DeploymentUserType $type,
    ): DeploymentUser {
        return new DeploymentUser()
            ->setUser($user)
            ->setDeployment($deployment)
            ->setType($type);
    }
}
