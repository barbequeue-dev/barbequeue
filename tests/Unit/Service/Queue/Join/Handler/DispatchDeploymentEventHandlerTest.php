<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Queue\Join\Handler;

use App\Entity\Deployment;
use App\Entity\Queue;
use App\Entity\QueuedUser;
use App\Entity\Workspace;
use App\Event\Deployment\DeploymentAddedEvent;
use App\Event\Deployment\DeploymentConfirmationRequiredEvent;
use App\Event\Deployment\DeploymentStartedEvent;
use App\Service\Queue\Context\ContextType;
use App\Service\Queue\Context\QueueContextInterface;
use App\Service\Queue\Join\Handler\DispatchDeploymentEventHandler;
use App\Service\Queue\Join\JoinQueueContext;
use App\Tests\Unit\LoggerAwareTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(DispatchDeploymentEventHandler::class)]
class DispatchDeploymentEventHandlerTest extends LoggerAwareTestCase
{
    #[Test]
    public function itShouldSupportJoinContextWithDeployment(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($this->createStub(Deployment::class));

        $handler = new DispatchDeploymentEventHandler(
            $this->getLogger(),
            $this->createStub(EventDispatcherInterface::class),
        );

        $this->assertTrue($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportJoinQueueContextWithQueuedUser(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($this->createStub(QueuedUser::class));

        $handler = new DispatchDeploymentEventHandler(
            $this->getLogger(),
            $this->createStub(EventDispatcherInterface::class),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportGenericQueueContext(): void
    {
        $handler = new DispatchDeploymentEventHandler(
            $this->getLogger(),
            $this->createStub(EventDispatcherInterface::class),
        );

        $this->assertFalse($handler->supports($this->createStub(QueueContextInterface::class)));
    }

    #[Test]
    public function itShouldReturnEarlyIfContextNotJoinQueueContext(): void
    {
        $this->expectNotToPerformAssertions();

        $handler = new DispatchDeploymentEventHandler(
            $this->getLogger(),
            $this->createStub(EventDispatcherInterface::class),
        );

        $handler->handle($this->createStub(QueueContextInterface::class));
    }

    #[Test]
    public function itShouldReturnEarlyIfQueuedUserNotDeployment(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($this->createStub(QueuedUser::class));

        $handler = new DispatchDeploymentEventHandler(
            $this->getLogger(),
            $this->createStub(EventDispatcherInterface::class),
        );

        $handler->handle($context);
    }

    #[Test]
    public function itShouldDispatchDeploymentStartedEventIfDeploymentIsActive(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($deployment = $this->createMock(Deployment::class));

        $deployment->method('isDraft')
            ->willReturn(false);

        $deployment->method('isPending')
            ->willReturn(false);

        $context->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createStub(Workspace::class));

        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue = $this->createMock(Queue::class));

        $queue->expects($this->once())
            ->method('getId')
            ->willReturn($queueId = 1);

        $context->expects($this->once())
            ->method('getId')
            ->willReturn($contextId = 'contextId');

        $context->expects($this->once())
            ->method('getType')
            ->willReturn($contextType = ContextType::JOIN);

        $this->expectsDebug('Dispatching {event} for new deployment on {queue} for {contextId} {contextType}', [
            'event' => DeploymentStartedEvent::class,
            'queue' => $queueId,
            'contextId' => $contextId,
            'contextType' => $contextType,
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($deployment, $workspace) {
                $this->assertInstanceOf(DeploymentStartedEvent::class, $event);
                $this->assertSame($deployment, $event->getDeployment());
                $this->assertSame($workspace, $event->getWorkspace());
                $this->assertFalse($event->shouldNotifyOwner());
            });

        $handler = new DispatchDeploymentEventHandler($this->getLogger(), $eventDispatcher);

        $handler->handle($context);
    }

    #[Test]
    public function itShouldDispatchDeploymentAddedEventIfDeploymentIsPending(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($deployment = $this->createMock(Deployment::class));

        $deployment->method('isDraft')
            ->willReturn(false);

        $deployment->expects($this->once())
            ->method('isPending')
            ->willReturN(true);

        $context->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createStub(Workspace::class));

        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue = $this->createMock(Queue::class));

        $queue->expects($this->once())
            ->method('getId')
            ->willReturn($queueId = 1);

        $context->expects($this->once())
            ->method('getId')
            ->willReturn($contextId = 'contextId');

        $context->expects($this->once())
            ->method('getType')
            ->willReturn($contextType = ContextType::JOIN);

        $this->expectsDebug('Dispatching {event} for new deployment on {queue} for {contextId} {contextType}', [
            'event' => DeploymentAddedEvent::class,
            'queue' => $queueId,
            'contextId' => $contextId,
            'contextType' => $contextType,
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($deployment, $workspace) {
                $this->assertInstanceOf(DeploymentAddedEvent::class, $event);
                $this->assertSame($deployment, $event->getDeployment());
                $this->assertSame($workspace, $event->getWorkspace());
                $this->assertFalse($event->shouldNotifyOwner());
            });

        $handler = new DispatchDeploymentEventHandler($this->getLogger(), $eventDispatcher);

        $handler->handle($context);
    }

    #[Test]
    public function itShouldDispatchDeploymentConfirmationRequiredEventIfDeploymentIsDraft(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($deployment = $this->createMock(Deployment::class));

        $deployment->expects($this->once())
            ->method('isDraft')
            ->willReturn(true);

        $deployment->expects($this->never())
            ->method('isPending');

        $context->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createStub(Workspace::class));

        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue = $this->createMock(Queue::class));

        $queue->expects($this->once())
            ->method('getId')
            ->willReturn($queueId = 1);

        $context->expects($this->once())
            ->method('getId')
            ->willReturn($contextId = 'contextId');

        $context->expects($this->once())
            ->method('getType')
            ->willReturn($contextType = ContextType::JOIN);

        $this->expectsDebug('Dispatching {event} for new deployment on {queue} for {contextId} {contextType}', [
            'event' => DeploymentConfirmationRequiredEvent::class,
            'queue' => $queueId,
            'contextId' => $contextId,
            'contextType' => $contextType,
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($deployment, $workspace) {
                $this->assertInstanceOf(DeploymentConfirmationRequiredEvent::class, $event);
                $this->assertSame($deployment, $event->getDeployment());
                $this->assertSame($workspace, $event->getWorkspace());
                $this->assertFalse($event->shouldNotifyOwner());
            });

        $handler = new DispatchDeploymentEventHandler($this->getLogger(), $eventDispatcher);

        $handler->handle($context);
    }
}
