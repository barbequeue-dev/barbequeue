<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Queue\Join\Handler;

use App\Entity\Deployment;
use App\Entity\DeploymentUser;
use App\Entity\Queue;
use App\Entity\QueuedUser;
use App\Entity\User;
use App\Enum\DeploymentUser as DeploymentUserType;
use App\Factory\DeploymentUserFactory;
use App\Service\Queue\Context\ContextType;
use App\Service\Queue\Context\QueueContextInterface;
use App\Service\Queue\Join\Handler\AddNotifyUsersToDeploymentHandler;
use App\Service\Queue\Join\JoinQueueContext;
use App\Tests\Unit\LoggerAwareTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(AddNotifyUsersToDeploymentHandler::class)]
class AddNotifyUsersToDeploymentHandlerTest extends LoggerAwareTestCase
{
    #[Test]
    public function itShouldSupportJoinQueueContextWithDeploymentAndNotEmptyUsers(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('isEmpty')
            ->willReturn(false);

        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($this->createStub(Deployment::class));

        $context->expects($this->once())
            ->method('getNotifyUsers')
            ->willReturn($collection);

        $handler = new AddNotifyUsersToDeploymentHandler(
            $this->getLogger(),
            $this->createStub(DeploymentUserFactory::class),
        );

        $this->assertTrue($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportContextWhereUsersCollectionIsEmpty(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);

        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($this->createStub(Deployment::class));

        $context->expects($this->once())
            ->method('getNotifyUsers')
            ->willReturn($collection);

        $handler = new AddNotifyUsersToDeploymentHandler(
            $this->getLogger(),
            $this->createStub(DeploymentUserFactory::class),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportContextWhereQueuedUserIsNotDeployment(): void
    {
        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($this->createStub(QueuedUser::class));

        $context->expects($this->never())
            ->method('getNotifyUsers')
            ->withAnyParameters();

        $handler = new AddNotifyUsersToDeploymentHandler(
            $this->getLogger(),
            $this->createStub(DeploymentUserFactory::class),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldNotSupportGenericContext(): void
    {
        $context = $this->createStub(QueueContextInterface::class);

        $handler = new AddNotifyUsersToDeploymentHandler(
            $this->getLogger(),
            $this->createStub(DeploymentUserFactory::class),
        );

        $this->assertFalse($handler->supports($context));
    }

    #[Test]
    public function itShouldReturnEarlyIfNotJoinQueueContext(): void
    {
        $this->expectNotToPerformAssertions();

        $handler = new AddNotifyUsersToDeploymentHandler(
            $this->getLogger(),
            $this->createStub(DeploymentUserFactory::class),
        );

        $handler->handle($this->createStub(QueueContextInterface::class));
    }

    #[Test]
    public function itShouldAddUsersToDeployment(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getId')
            ->willReturn($queueId = 1);

        $context = $this->createMock(JoinQueueContext::class);
        $context->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $context->expects($this->once())
            ->method('getId')
            ->willReturn($contextId = 'contextId');

        $context->expects($this->once())
            ->method('getType')
            ->willReturn($contextType = ContextType::JOIN);

        $context->expects($this->once())
            ->method('getNotifyUsers')
            ->willReturN(new ArrayCollection([$user = $this->createStub(User::class)]));

        $deployment = $this->createMock(Deployment::class);

        $deploymentUserFactory = $this->createMock(DeploymentUserFactory::class);
        $deploymentUserFactory->expects($this->once())
            ->method('create')
            ->with($user, $deployment, DeploymentUserType::NOTIFY)
            ->willReturn($deploymentUser = $this->createStub(DeploymentUser::class));

        $deployment->expects($this->once())
            ->method('addUser')
            ->with($deploymentUser)
            ->willReturnSelf();

        $context->expects($this->once())
            ->method('getQueuedUser')
            ->willReturn($deployment);

        $this->expectsDebug('Adding users to notify to deployment on {queue} {contextId} {contextType}', [
            'queue' => $queueId,
            'contextId' => $contextId,
            'contextType' => $contextType->value,
        ]);

        $handler = new AddNotifyUsersToDeploymentHandler($this->getLogger(), $deploymentUserFactory);

        $handler->handle($context);
    }
}
