<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Deployment;
use App\Entity\DeploymentQueue;
use App\Entity\DeploymentQueueSettings;
use App\Entity\DeploymentUser;
use App\Entity\User;
use App\Enum\DeploymentConfirmation;
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
        $user = new DeploymentUser()
            ->setUser($user)
            ->setDeployment($deployment)
            ->setType($type);

        if ($type === DeploymentUserType::CONFIRM) {

        }

        return $user;
    }

    private function createConfirmations(Deployment $deployment, DeploymentUser $deploymentUser): void
    {
        /** @var DeploymentQueue $queue */
        $queue = $deployment->getQueue();

        /** @var DeploymentQueueSettings $settings */
        $settings = $queue->getSettings();

        if ($settings->getJoinConfirmationTimeoutMinutes() !== null) {
            $this->deploymentConfirmationFactory->create($deploymentUser, DeploymentConfirmation::JOIN);
        }

        if ($settings->getStartConfirmationTimeoutMinutes() !== null) {
            $this->deploymentConfirmationFactory->create($deploymentUser, DeploymentConfirmation::START);
        }

        if ($settings->getCompletionConfirmationTimeoutMinutes() !== null) {
            $this->deploymentConfirmationFactory->create($deploymentUser, DeploymentConfirmation::COMPLETION);
        }
    }
}
