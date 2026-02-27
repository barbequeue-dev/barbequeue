<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Deployment;
use App\Entity\DeploymentQueue;
use App\Entity\Repository;
use App\Entity\User;
use App\Enum\DeploymentStatus;
use App\Enum\QueueBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(Deployment::class)]
class DeploymentTest extends KernelTestCase
{
    #[Test]
    public function itShouldReturnPassedParameters(): void
    {
        $deployment = new Deployment()
            ->setRepository($repository = $this->createStub(Repository::class))
            ->setLink($link = 'link')
            ->setDescription($description = 'description')
            ->setStatus($status = DeploymentStatus::ACTIVE);

        $this->assertSame($repository, $deployment->getRepository());
        $this->assertSame($link, $deployment->getLink());
        $this->assertSame($description, $deployment->getDescription());
        $this->assertSame($status, $deployment->getStatus());

        $notifyUser = $this->createStub(User::class);

        $deployment->addUser($notifyUser);

        $this->assertCount(1, $deployment->getNotifyUsers());
        $this->assertEquals($notifyUser, $deployment->getNotifyUsers()->first());

        $deployment->removeNotifyUser($notifyUser);

        $this->assertCount(0, $deployment->getNotifyUsers());
        $this->assertFalse($deployment->getNotifyUsers()->first());

        $this->assertTrue($deployment->isActive());
    }

    #[Test]
    public function itShouldReturnRepositoryIsBlockedByDeployment(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('isBlockedByDeployment')
            ->willReturn(true);

        $deployment = new Deployment()
            ->setRepository($repository);

        $this->assertTrue($deployment->isBlockedByRepository());
    }

    #[Test]
    public function itShouldReturnFalseOnIsBlockedByQueueIfQueueAllowsSimultaneous(): void
    {
        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ALLOW_SIMULTANEOUS);

        $deployment = new Deployment()
            ->setQueue($queue);

        $this->assertFalse($deployment->isBlockedByQueue());
    }

    #[Test]
    public function itShouldReturnFalseOnIsBlockedByQueueIfQueueBehaviourEnforceQueueAndDeploymentIsFirstInQueue(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ENFORCE_QUEUE);

        $queue->expects($this->once())
            ->method('getFirstPlace')
            ->willReturn($deployment);

        $deployment->setQueue($queue);

        $this->assertFalse($deployment->isBlockedByQueue());
    }

    #[Test]
    public function itShouldReturnTrueOnIsBlockedByQueueIfQueueBehaviourEnforceQueueAndDeploymentIsNotFirstInQueue(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ENFORCE_QUEUE);

        $queue->expects($this->once())
            ->method('getFirstPlace')
            ->willReturn($this->createStub(Deployment::class));

        $deployment->setQueue($queue);

        $this->assertTrue($deployment->isBlockedByQueue());
    }

    #[Test]
    public function itShouldReturnTrueOnIsBlockedByQueueIfQueueBehaviourAllowJumpsAndQueueHasActiveDeployment(): void
    {
        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->exactly(2))
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ALLOW_JUMPS);

        $queue->expects($this->once())
            ->method('hasActiveDeployment')
            ->willReturn(true);

        $deployment = new Deployment()
            ->setQueue($queue);

        $this->assertTrue($deployment->isBlockedByQueue());
    }

    #[Test]
    public function itShouldReturnTrueIfDeploymentInQueueIsNotBlockedByRepository(): void
    {
        $firstDeploymentInQueue = $this->createMock(Deployment::class);
        $firstDeploymentInQueue->expects($this->once())
            ->method('isBlockedByRepository')
            ->willReturn(true);

        $deploymentInQueue = $this->createMock(Deployment::class);
        $deploymentInQueue->expects($this->once())
            ->method('isBlockedByRepository')
            ->willReturn(false);

        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->exactly(2))
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ALLOW_JUMPS);

        $queue->expects($this->once())
            ->method('hasActiveDeployment')
            ->willReturn(false);

        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([$firstDeploymentInQueue, $deploymentInQueue, $deployment]);

        $deployment->setQueue($queue);

        $this->assertTrue($deployment->isBlockedByQueue());
    }

    #[Test]
    public function itShouldReturnFalseIfAllDeploymentsInFrontAreBlockedByRepository(): void
    {
        $firstDeploymentInQueue = $this->createMock(Deployment::class);
        $firstDeploymentInQueue->expects($this->once())
            ->method('isBlockedByRepository')
            ->willReturn(true);

        $deploymentInQueue = $this->createMock(Deployment::class);
        $deploymentInQueue->expects($this->once())
            ->method('isBlockedByRepository')
            ->willReturn(true);

        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->exactly(2))
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ALLOW_JUMPS);

        $queue->expects($this->once())
            ->method('hasActiveDeployment')
            ->willReturn(false);

        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([$firstDeploymentInQueue, $deploymentInQueue, $deployment]);

        $deployment->setQueue($queue);

        $this->assertFalse($deployment->isBlockedByQueue());
    }

    #[Test]
    public function itShouldReturnFalseOnIsBlockedByAllowJumpQueueIfQueueBehaviourNotAllowJumps(): void
    {
        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ENFORCE_QUEUE);

        $deployment = new Deployment()
            ->setQueue($queue);

        $this->assertFalse($deployment->isBlockedByAllowJumpQueue());
    }

    #[Test]
    public function itShouldReturnFalseIfQueueEmpty(): void
    {
        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ALLOW_JUMPS);

        $queue->expects($this->once())
            ->method('hasActiveDeployment')
            ->willReturn(false);

        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([]);

        $deployment = new Deployment()
            ->setQueue($queue);

        $this->assertFalse($deployment->isBlockedByAllowJumpQueue());
    }

    #[Test]
    public function itShouldReturnNullOnGetBlockedIfDeploymentIsActive(): void
    {
        $deployment = new Deployment()
            ->setStatus(DeploymentStatus::ACTIVE);

        $this->assertNull($deployment->getBlocker());
    }

    #[Test]
    public function itShouldReturnRepositoryBlockingDeploymentIfFirstInQueue(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getFirstPlace')
            ->willReturn($deployment);

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('getBlockingDeployment')
            ->willReturn($blocker = $this->createStub(Deployment::class));

        $deployment->setQueue($queue)
            ->setRepository($repository);

        $this->assertSame($blocker, $deployment->getBlocker());
    }

    #[Test]
    public function itShouldReturnRepositoryBlockerIfNotFirstInQueueButAllowSimultaneous(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getFirstPlace')
            ->willReturn($this->createStub(Deployment::class));

        $queue->expects($this->once())
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ALLOW_SIMULTANEOUS);

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('getBlockingDeployment')
            ->willReturn($blocker = $this->createStub(Deployment::class));

        $deployment->setQueue($queue)
            ->setRepository($repository);

        $this->assertSame($blocker, $deployment->getBlocker());
    }

    #[Test]
    public function itShouldReturnQueueFirstPlaceIfNotSimultaneousQueue(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->exactly(2))
            ->method('getFirstPlace')
            ->willReturn($blocker = $this->createStub(Deployment::class));

        $queue->expects($this->once())
            ->method('getBehaviour')
            ->willReturn(QueueBehaviour::ALLOW_JUMPS);

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->never())
            ->method('getBlockingDeployment');

        $deployment->setQueue($queue)
            ->setRepository($repository);

        $this->assertSame($blocker, $deployment->getBlocker());
    }

    #[Test]
    public function itShouldReturnEmptyStringIfQueueEmpty(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([]);

        $deployment->setQueue($queue);

        $this->assertEquals('', $deployment->getPlacement());
    }

    #[Test]
    public function itShouldReturnCorrectPlacement(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([$deployment]);

        $deployment->setQueue($queue);

        $this->assertEquals('1st', $deployment->getPlacement());
    }

    #[Test]
    public function itShouldReturnCorrectPlacementIfSecondInQueue(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([$this->createStub(Deployment::class), $deployment]);

        $deployment->setQueue($queue);

        $this->assertEquals('2nd', $deployment->getPlacement());
    }

    #[Test]
    public function itShouldReturnCorrectPlacementIfThirdInQueue(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([$this->createStub(Deployment::class), $this->createStub(Deployment::class), $deployment]);

        $deployment->setQueue($queue);

        $this->assertEquals('3rd', $deployment->getPlacement());
    }

    #[Test]
    public function itShouldReturnCorrectPlacementIfFourthInQueue(): void
    {
        $deployment = new Deployment();

        $queue = $this->createMock(DeploymentQueue::class);
        $queue->expects($this->once())
            ->method('getSortedUsers')
            ->willReturn([
                $this->createStub(Deployment::class),
                $this->createStub(Deployment::class),
                $this->createStub(Deployment::class),
                $this->createStub(Deployment::class),
            ]);

        $deployment->setQueue($queue);

        $this->assertEquals('4th', $deployment->getPlacement());
    }
}
