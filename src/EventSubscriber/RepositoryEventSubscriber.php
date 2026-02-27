<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Calculator\ClosestFiveMinutesCalculator;
use App\Entity\DeploymentQueue;
use App\Entity\DeploymentQueueSettings;
use App\Event\Deployment\DeploymentAwaitingDeploymentEvent;
use App\Event\Deployment\DeploymentStartedEvent;
use App\Event\Repository\RepositoryUpdatedEvent;
use App\Resolver\Repository\NextDeploymentResolver;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class RepositoryEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NextDeploymentResolver $nextDeploymentResolver,
        private ClosestFiveMinutesCalculator $closestFiveMinutesCalculator,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RepositoryUpdatedEvent::class => 'handleUpdated',
        ];
    }

    public function handleUpdated(RepositoryUpdatedEvent $event): void
    {
        $repository = $event->getRepository();

        if (null === $repository) {
            return;
        }

        $nextDeployment = $this->nextDeploymentResolver->resolve($repository);

        if (null === $nextDeployment) {
            return;
        }

        if (null !== $expiryMinutes = $nextDeployment->getExpiryMinutes()) {
            $nextDeployment->setExpiresAt(
                $this->closestFiveMinutesCalculator->calculate(CarbonImmutable::now()->addMinutes($expiryMinutes)),
            );
        }

        /** @var DeploymentQueue $queue */
        $queue = $nextDeployment->getQueue();

        /** @var DeploymentQueueSettings $settings */
        $settings = $queue->getSettings();

        if (null === $settings->getStartConfirmationTimeoutMinutes()) {
            $nextDeployment->setStartedAt(CarbonImmutable::now());
        }

        $this->entityManager->persist($nextDeployment);

        $workspace = $queue->getWorkspace();

        if (!$event->areNotificationsEnabled()) {
            return;
        }

        if (null === $workspace) {
            return;
        }

        match (true) {
            $nextDeployment->isActive() => $this->eventDispatcher->dispatch(new DeploymentStartedEvent($nextDeployment, $workspace, true)),
            $nextDeployment->isPending() => $this->eventDispatcher->dispatch(new DeploymentAwaitingDeploymentEvent($nextDeployment, $workspace, true)),
            default => $this->logger->warning('{deployment} is in invalid status', ['deployment' => $nextDeployment->getId()]),
        };
    }
}
