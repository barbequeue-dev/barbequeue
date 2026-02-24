<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Calculator\ClosestFiveMinutesCalculator;
use App\Entity\Deployment;
use App\Entity\DeploymentQueue;
use App\Entity\Repository;
use App\Entity\Workspace;
use App\Enum\DeploymentStatus;
use App\Event\Deployment\DeploymentStartedEvent;
use App\Event\Repository\RepositoryUpdatedEvent;
use App\EventSubscriber\RepositoryEventSubscriber;
use App\Resolver\Repository\NextDeploymentResolver;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(RepositoryEventSubscriber::class)]
class RepositoryEventSubscriberTest extends KernelTestCase
{
    #[Test]
    public function itShouldSubscribeToCorrectEvents(): void
    {
        $subscribedEvents = RepositoryEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(RepositoryUpdatedEvent::class, $subscribedEvents);
        $this->assertEquals('handleUpdated', $subscribedEvents[RepositoryUpdatedEvent::class]);
    }

    #[Test]
    public function itShouldReturnEarlyIfEventRepositoryIsNull(): void
    {
        $event = $this->createMock(RepositoryUpdatedEvent::class);
        $event->expects($this->once())
            ->method('getRepository')
            ->willReturn(null);

        $subscriber = new RepositoryEventSubscriber(
            $this->createStub(NextDeploymentResolver::class),
            $this->createStub(ClosestFiveMinutesCalculator::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        $subscriber->handleUpdated($event);
    }

    #[Test]
    public function itShouldReturnIfNextDeploymentIsNull(): void
    {
        $event = $this->createMock(RepositoryUpdatedEvent::class);
        $event->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository = $this->createStub(Repository::class));

        $resolver = $this->createMock(NextDeploymentResolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($repository)
            ->willReturn(null);

        $subscriber = new RepositoryEventSubscriber(
            $resolver,
            $this->createStub(ClosestFiveMinutesCalculator::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        $subscriber->handleUpdated($event);
    }

    #[Test]
    public function itShouldSetExpiresAtIfExpiryMinutesSetOnDeployment(): void
    {
        $event = $this->createMock(RepositoryUpdatedEvent::class);
        $event->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository = $this->createStub(Repository::class));

        $event->expects($this->once())
            ->method('areNotificationsEnabled')
            ->willReturn(true);

        $deployment = $this->createMock(Deployment::class);
        $deployment->expects($this->once())
            ->method('getExpiryMinutes')
            ->willReturn(5);

        $resolver = $this->createMock(NextDeploymentResolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($repository)
            ->willReturn($deployment);

        $calculator = $this->createMock(ClosestFiveMinutesCalculator::class);
        $calculator->expects($this->once())
            ->method('calculate')
            ->willReturn($expiresAt = CarbonImmutable::now());

        $deployment->expects($this->once())
            ->method('setExpiresAt')
            ->with($expiresAt)
            ->willReturnSelf();

        $deployment->expects($this->once())
            ->method('setStatus')
            ->with(DeploymentStatus::ACTIVE)
            ->willReturnSelf();

        $deployment->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue = $this->createMock(DeploymentQueue::class));

        $deployment->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeploymentStatus::ACTIVE);

        $queue->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createStub(Workspace::class));

        $queue->expects($this->once())
            ->method('shouldConfirmDeployments')
            ->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($deployment);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($deployment, $workspace) {
                $this->assertInstanceOf(DeploymentStartedEvent::class, $event);
                $this->assertSame($deployment, $event->getDeployment());
                $this->assertSame($workspace, $workspace);
            });

        $subscriber = new RepositoryEventSubscriber(
            $resolver,
            $calculator,
            $entityManager,
            $eventDispatcher,
            $this->createStub(LoggerInterface::class),
        );

        $subscriber->handleUpdated($event);
    }

    #[Test]
    public function itShouldNotSetExpiresAtIfExpiryMinutesNotSetOnDeployment(): void
    {
        $event = $this->createMock(RepositoryUpdatedEvent::class);
        $event->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository = $this->createStub(Repository::class));

        $deployment = $this->createMock(Deployment::class);
        $deployment->expects($this->once())
            ->method('getExpiryMinutes')
            ->willReturn(null);

        $deployment->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue = $this->createStub(DeploymentQueue::class));

        $queue->method('shouldConfirmDeployments')
            ->willReturn(false);

        $resolver = $this->createMock(NextDeploymentResolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($repository)
            ->willReturn($deployment);

        $calculator = $this->createMock(ClosestFiveMinutesCalculator::class);
        $calculator->expects($this->never())
            ->method('calculate')
            ->willReturn($expiresAt = CarbonImmutable::now());

        $deployment->expects($this->never())
            ->method('setExpiresAt')
            ->with($expiresAt)
            ->willReturnSelf();

        $deployment->expects($this->once())
            ->method('setStatus')
            ->with(DeploymentStatus::ACTIVE)
            ->willReturnSelf();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($deployment);

        $subscriber = new RepositoryEventSubscriber(
            $resolver,
            $calculator,
            $entityManager,
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        $subscriber->handleUpdated($event);
    }
}
