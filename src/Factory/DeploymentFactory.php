<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Deployment;
use App\Entity\DeploymentQueue;
use Carbon\CarbonImmutable;

class DeploymentFactory
{
    public function createForDeploymentQueue(DeploymentQueue $deploymentQueue): Deployment
    {
        $deployment = new Deployment()->setCreatedAtNow();

        $deploymentQueue->addQueuedUser($deployment);

        if (null === $deploymentQueue->getSettings()?->getJoinConfirmationTimeoutMinutes()) {
            $deployment->setJoinedAt(CarbonImmutable::now());
        }

        return $deployment;
    }
}
