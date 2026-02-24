<?php

declare(strict_types=1);

namespace App\EventListener\Notifier;

use App\Event\Deployment\DeploymentAwaitingDeploymentEvent;
use App\Slack\Response\PrivateMessage\Factory\Deployment\AwaitingDeploymentMessageFactory;
use App\Slack\Response\PrivateMessage\PrivateMessageHandler;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: DeploymentAwaitingDeploymentEvent::class, method: 'handle')]
readonly class DeploymentAwaitingDeploymentNotifier
{
    public function __construct(
        private AwaitingDeploymentMessageFactory $privateMessageFactory,
        private PrivateMessageHandler $privateMessageHandler,
    ) {
    }

    public function handle(DeploymentAwaitingDeploymentEvent $event): void
    {
        $this->privateMessageHandler->handle(
            $this->privateMessageFactory->create($event->getDeployment(), $event->getWorkspace()),
        );
    }
}
