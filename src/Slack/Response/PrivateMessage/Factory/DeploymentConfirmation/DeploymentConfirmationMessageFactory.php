<?php

declare(strict_types=1);

namespace App\Slack\Response\PrivateMessage\Factory\DeploymentConfirmation;

use App\Entity\DeploymentConfirmation;
use App\Enum\DeploymentConfirmation as DeploymentConfirmationType;
use App\Slack\Response\PrivateMessage\SlackPrivateMessage;

readonly class DeploymentConfirmationMessageFactory
{
    public function __construct(
        private CompletionConfirmationMessageFactory $completionConfirmationMessageFactory,
        private JoinConfirmationMessageFactory $joinConfirmationMessageFactory,
        private StartConfirmationMessageFactory $startConfirmationMessageFactory,
    ) {
    }

    public function create(DeploymentConfirmation $confirmation): SlackPrivateMessage
    {
        return match ($confirmation->getType()) {
            DeploymentConfirmationType::JOIN => $this->joinConfirmationMessageFactory->create($confirmation),
            DeploymentConfirmationType::START => $this->startConfirmationMessageFactory->create($confirmation),
            default => $this->completionConfirmationMessageFactory->create($confirmation),
        };
    }
}
