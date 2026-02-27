<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Queue\Join\Handler;

use App\Entity\DeploymentQueue;
use App\Entity\Queue;
use App\Entity\User;
use App\Entity\Workspace;
use App\Resolver\UserResolver;
use App\Service\Queue\Context\ContextType;
use App\Service\Queue\Context\QueueContextInterface;
use App\Service\Queue\Join\Handler\ResolveNotifyUsersHandler;
use App\Service\Queue\Join\JoinQueueContext;
use App\Tests\Unit\LoggerAwareTestCase;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ResolveNotifyUsersHandler::class)]
class ResolveNotifyUsersHandlerTest extends LoggerAwareTestCase
{
    #[Test]
    public function itShouldSupportJoinQueueContextWithNotEmptyNotifyUsersAndDeploymentQueueWithEmptyUsersOnContext(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getNotifyUserIds')
            ->willReturn(['notifyUser']);

        $context->expects($this->once())
            ->method('getNotifyUsers')
            ->willReturn($collection = $this->createMock(Collection::class));

        $collection->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);

        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($this->createStub(DeploymentQueue::class));

        $handler = new ResolveNotifyUsersHandler(
            $this->createStub(UserResolver::class),
            $this->getLogger(),
        );

        $this->assertTrue($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportJoinQueueContextWithNotEmptyUsers(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getNotifyUserIds')
            ->willReturn(['notifyUser']);

        $context->expects($this->once())
            ->method('getNotifyUsers')
            ->willReturn($collection = $this->createMock(Collection::class));

        $collection->expects($this->once())
            ->method('isEmpty')
            ->willReturn(false);

        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($this->createStub(DeploymentQueue::class));

        $handler = new ResolveNotifyUsersHandler(
            $this->createStub(UserResolver::class),
            $this->getLogger(),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportJoinQueueContextWithNotEmptyNotifyUsersAndQueue(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getNotifyUserIds')
            ->willReturn(['notifyUser']);

        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($this->createStub(Queue::class));

        $handler = new ResolveNotifyUsersHandler(
            $this->createStub(UserResolver::class),
            $this->getLogger(),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportJoinQueueContextWithEmptyNotifyUsers(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getNotifyUserIds')
            ->willReturn([]);

        $context->expects($this->never())
            ->method('getQueue')
            ->willReturn($this->createStub(Queue::class));

        $handler = new ResolveNotifyUsersHandler(
            $this->createStub(UserResolver::class),
            $this->getLogger(),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportGenericQueueContext(): void
    {
        $context = $this->createStub(QueueContextInterface::class);

        $handler = new ResolveNotifyUsersHandler(
            $this->createStub(UserResolver::class),
            $this->getLogger(),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldReturnEarlyIfNotJoinQueueContext(): void
    {
        $this->expectNotToPerformAssertions();

        $handler = new ResolveNotifyUsersHandler(
            $this->createStub(UserResolver::class),
            $this->getLogger(),
        );

        $handler->handle($this->createStub(QueueContextInterface::class));
    }

    #[Test]
    public function itShouldResolveUsersPassedOnContext(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getId')
            ->willReturn($contextId = 'contextId');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getId')
            ->willReturn($queueId = 1);

        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $context->expects($this->once())
            ->method('getType')
            ->willReturn($type = ContextType::JOIN);

        $context->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createStub(Workspace::class));

        $context->expects($this->once())
            ->method('getNotifyUserIds')
            ->willReturn([$firstUserId = 'firstUserId', $secondUserId = 'secondUserId']);

        $firstUser = $this->createStub(User::class);
        $secondUser = $this->createStub(User::class);

        $callCount = 0;
        $resolver = $this->createMock(UserResolver::class);
        $resolver->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnCallback(function ($userId, $workspaceArgument) use ($workspace, $firstUserId, $firstUser, $secondUser, $secondUserId, &$callCount) {
                $this->assertSame($workspace, $workspaceArgument);

                if (1 === ++$callCount) {
                    $this->assertSame($firstUserId, $userId);

                    return $firstUser;
                }

                $this->assertSame($secondUserId, $userId);

                return $secondUser;
            });

        $addUserCallCount = 0;
        $context->expects($this->exactly(2))
            ->method('addNotifyUser')
            ->willReturnCallback(function ($user) use ($firstUser, $secondUser, &$addUserCallCount) {
                if (1 === ++$addUserCallCount) {
                    $this->assertSame($firstUser, $user);

                    return;
                }

                $this->assertSame($secondUser, $user);
            });

        $this->expectsDebug('Resolving users to notify about deployment {queue} {contextId} {contextType}', [
            'queue' => $queueId,
            'contextId' => $contextId,
            'contextType' => $type->value,
        ]);

        $handler = new ResolveNotifyUsersHandler(
            $resolver,
            $this->getLogger(),
        );

        $handler->handle($context);
    }
}
