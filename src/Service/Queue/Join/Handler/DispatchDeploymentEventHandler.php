<?php

declare(strict_types=1);

namespace App\Service\Queue\Join\Handler;

use App\Entity\Deployment;
use App\Event\Deployment\DeploymentAddedEvent;
use App\Event\Deployment\DeploymentConfirmationRequiredEvent;
use App\Event\Deployment\DeploymentStartedEvent;
use App\Service\Queue\Context\QueueContextInterface;
use App\Service\Queue\Join\JoinQueueContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 50)]
readonly class DispatchDeploymentEventHandler implements JoinQueueHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function supports(QueueContextInterface $context): bool
    {
        return $context instanceof JoinQueueContext && $context->getQueuedUser() instanceof Deployment;
    }

    public function handle(QueueContextInterface $context): void
    {
        if (!$context instanceof JoinQueueContext) {
            return;
        }

        $deployment = $context->getQueuedUser();

        if (!$deployment instanceof Deployment) {
            return;
        }

        $event = match (true) {
            $deployment->isDraft() => new DeploymentConfirmationRequiredEvent($deployment, $context->getWorkspace()),
            $deployment->isPending() => new DeploymentAddedEvent($deployment, $context->getWorkspace()),
            default => new DeploymentStartedEvent($deployment, $context->getWorkspace()),
        };

        $this->logger->debug('Dispatching {event} for new deployment on {queue} for {contextId} {contextType}', [
            'event' => $event::class,
            'queue' => $context->getQueue()->getId(),
            'contextId' => $context->getId(),
            'contextType' => $context->getType(),
        ]);

        $this->eventDispatcher->dispatch($event);
    }
}
