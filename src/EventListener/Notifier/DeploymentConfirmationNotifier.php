<?php

declare(strict_types=1);

namespace App\EventListener\Notifier;

use App\Entity\DeploymentConfirmation;
use App\Enum\DeploymentConfirmation as DeploymentConfirmationType;
use App\Event\Deployment\DeploymentConfirmationRequiredEvent;
use App\Slack\Response\PrivateMessage\Factory\DeploymentConfirmation\DeploymentConfirmationMessageFactory;
use App\Slack\Response\PrivateMessage\PrivateMessageHandler;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: DeploymentConfirmationRequiredEvent::class, method: 'handle')]
readonly class DeploymentConfirmationNotifier
{
    public function __construct(
        private PrivateMessageHandler $privateMessageHandler,
        private DeploymentConfirmationMessageFactory $deploymentConfirmationMessageFactory,
    ) {
    }

    public function handle(DeploymentConfirmationRequiredEvent $event): void
    {
        $deployment = $event->getDeployment();

        /** @var Collection<int, DeploymentConfirmation> $confirmations */
        $confirmations = match (true) {
            $deployment->isDraft() => $deployment->getConfirmationsByType(DeploymentConfirmationType::JOIN),
            $deployment->isPending() => $deployment->getConfirmationsByType(DeploymentConfirmationType::START),
            default => $deployment->getConfirmationsByType(DeploymentConfirmationType::COMPLETION),
        };

        foreach ($confirmations as $confirmation) {
            $this->privateMessageHandler->handle(
                $this->deploymentConfirmationMessageFactory->create($confirmation),
            );
        }
    }
}
