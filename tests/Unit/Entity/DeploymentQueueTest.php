<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Deployment;
use App\Entity\DeploymentQueue;
use App\Entity\Repository;
use App\Enum\Queue;
use App\Enum\QueueBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(DeploymentQueue::class)]
class DeploymentQueueTest extends KernelTestCase
{
    #[Test]
    public function itShouldReturnPassedParameters(): void
    {
        $queue = new DeploymentQueue()
            ->setBehaviour($behaviour = QueueBehaviour::ALLOW_SIMULTANEOUS);

        $this->assertSame($behaviour, $queue->getBehaviour());

        $repository = $this->createStub(Repository::class);

        $this->assertCount(0, $queue->getRepositories());
        $queue->addRepository($repository);
        $this->assertCount(1, $queue->getRepositories());
        $this->assertSame($repository, $queue->getRepositories()->first());
        $queue->removeRepository($repository);
        $this->assertFalse($queue->getRepositories()->first());

        $queue->addRepository($repository)
            ->clearRepositories();

        $this->assertCount(0, $queue->getRepositories());
        $this->assertEquals(Queue::DEPLOYMENT, $queue->getType());
    }

    #[Test]
    public function itShouldCreateCommaSeparatedListOfRepositoryNames(): void
    {
        $firstRepository = $this->createMock(Repository::class);
        $firstRepository->expects($this->once())
            ->method('getName')
            ->willReturn('firstRepository');

        $secondRepository = $this->createMock(Repository::class);
        $secondRepository->expects($this->once())
            ->method('getName')
            ->willReturn('secondRepository');

        $queue = new DeploymentQueue()
            ->addRepository($firstRepository)
            ->addRepository($secondRepository);

        $this->assertEquals('firstRepository, secondRepository', $queue->getRepositoryList());
    }

    #[Test]
    public function itShouldCreateCommaSeparatedListOfRepositoryNamesSurroundedByBackticks(): void
    {
        $firstRepository = $this->createMock(Repository::class);
        $firstRepository->expects($this->once())
            ->method('getName')
            ->willReturn('firstRepository');

        $secondRepository = $this->createMock(Repository::class);
        $secondRepository->expects($this->once())
            ->method('getName')
            ->willReturn('secondRepository');

        $queue = new DeploymentQueue()
            ->addRepository($firstRepository)
            ->addRepository($secondRepository);

        $this->assertEquals('`firstRepository`, `secondRepository`', $queue->getPrettyRepositoryList());
    }

    #[Test]
    public function itShouldReturnTrueIfQueueHasActiveDeployment(): void
    {
        $firstDeployment = $this->createMock(Deployment::class);
        $firstDeployment->expects($this->once())
            ->method('isActive')
            ->willReturn(false);

        $activeDeployment = $this->createMock(Deployment::class);
        $activeDeployment->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $thirdDeployment = $this->createMock(Deployment::class);
        $thirdDeployment->expects($this->never())
            ->method('isActive');

        $queue = new DeploymentQueue()
            ->addQueuedUser($firstDeployment)
            ->addQueuedUser($activeDeployment)
            ->addQueuedUser($thirdDeployment);

        $this->assertTrue($queue->hasActiveDeployment());
    }

    #[Test]
    public function itShouldReturnTrueIfFirstPlaceIsPassedDeployment(): void
    {
        $deployment = $this->createStub(Deployment::class);
        $deployment->method('isPending')
            ->willReturn(true);

        $queue = new DeploymentQueue()
            ->addQueuedUser($deployment);

        $this->assertTrue($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldReturnFalseIfDeploymentNotInQueue(): void
    {
        $deployment = $this->createStub(Deployment::class);

        $queue = new DeploymentQueue();

        $this->assertFalse($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldReturnFalseIfBehaviourEnforce(): void
    {
        $queue = new DeploymentQueue()
            ->addQueuedUser($this->createStub(Deployment::class))
            ->addQueuedUser($deployment = $this->createStub(Deployment::class))
            ->setBehaviour(QueueBehaviour::ENFORCE_QUEUE);

        $this->assertFalse($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldReturnFalseIfBehaviourAllowJumpAndHasActiveDeployment(): void
    {
        $activeDeployment = $this->createMock(Deployment::class);
        $activeDeployment->expects($this->exactly(2))
            ->method('isActive')
            ->willReturn(true);

        $queue = new DeploymentQueue()
            ->addQueuedUser($activeDeployment)
            ->addQueuedUser($deployment = $this->createStub(Deployment::class))
            ->setBehaviour(QueueBehaviour::ALLOW_JUMPS);

        $this->assertFalse($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldReturnFalseIfPreviousInQueueIsNotBlockedByRepositoryForAllowJump(): void
    {
        $firstDeployment = $this->createMock(Deployment::class);
        $firstDeployment->expects($this->exactly(2))
            ->method('isPending')
            ->willReturn(true);

        $firstDeployment->expects($this->once())
            ->method('isBlockedByRepository')
            ->willReturn(false);

        $queue = new DeploymentQueue()
            ->addQueuedUser($firstDeployment)
            ->addQueuedUser($deployment = $this->createStub(Deployment::class))
            ->setBehaviour(QueueBehaviour::ALLOW_JUMPS);

        $deployment->method('isPending')
            ->willReturn(true);

        $this->assertFalse($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldReturnTrueIfPreviousInQueueIsBlockedByRepositoryForAllowJump(): void
    {
        $firstDeployment = $this->createMock(Deployment::class);
        $firstDeployment->expects($this->exactly(2))
            ->method('isPending')
            ->willReturn(true);

        $firstDeployment->expects($this->once())
            ->method('isBlockedByRepository')
            ->willReturn(true);

        $queue = new DeploymentQueue()
            ->addQueuedUser($firstDeployment)
            ->addQueuedUser($deployment = $this->createStub(Deployment::class))
            ->setBehaviour(QueueBehaviour::ALLOW_JUMPS);

        $this->assertTrue($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldReturnFalseIfPreviousInQueueIsDeployingToSameRepositoryForAllowSimultaneous(): void
    {
        $firstDeployment = $this->createMock(Deployment::class);
        $firstDeployment->expects($this->exactly(2))
            ->method('isActive')
            ->willReturn(true);

        $firstDeployment->expects($this->exactly(2))
            ->method('getRepository')
            ->willReturn($repository = $this->createStub(Repository::class));

        $deployment = $this->createMock(Deployment::class);
        $deployment->expects($this->exactly(2))
            ->method('getRepository')
            ->willReturn($repository);

        $queue = new DeploymentQueue()
            ->addQueuedUser($firstDeployment)
            ->addQueuedUser($deployment)
            ->setBehaviour(QueueBehaviour::ALLOW_SIMULTANEOUS);

        $this->assertFalse($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldReturnTrueIfPreviousInQueueIsDeployingToDifferentRepositoryForAllowSimultaneous(): void
    {
        $firstDeployment = $this->createMock(Deployment::class);
        $firstDeployment->expects($this->exactly(2))
            ->method('isActive')
            ->willReturn(true);

        $firstDeployment->expects($this->exactly(1))
            ->method('getRepository')
            ->willReturn($this->createStub(Repository::class));

        $deployment = $this->createMock(Deployment::class);
        $deployment->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->createStub(Repository::class));

        $queue = new DeploymentQueue()
            ->addQueuedUser($firstDeployment)
            ->addQueuedUser($deployment)
            ->setBehaviour(QueueBehaviour::ALLOW_SIMULTANEOUS);

        $this->assertTrue($queue->isDeploymentAllowed($deployment));
    }

    #[Test]
    public function itShouldPutActiveDeploymentsFirst(): void
    {
        $firstDeployment = $this->createMock(Deployment::class);
        $firstDeployment->expects($this->once())
            ->method('isPending')
            ->willReturn(true);

        $secondDeployment = $this->createMock(Deployment::class);
        $secondDeployment->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $queue = new DeploymentQueue();
        $queue->addQueuedUser($firstDeployment)->addQueuedUser($secondDeployment);

        $sortedDeployments = $queue->getSortedUsers();

        $this->assertCount(2, $sortedDeployments);
        $this->assertSame($secondDeployment, $sortedDeployments[0]);
        $this->assertSame($firstDeployment, $sortedDeployments[1]);
    }

    #[Test]
    public function itShouldReturnActiveDeployments(): void
    {
        $firstDeployment = $this->createStub(Deployment::class);

        $secondDeployment = $this->createMock(Deployment::class);
        $secondDeployment->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $queue = new DeploymentQueue();
        $queue->addQueuedUser($firstDeployment)->addQueuedUser($secondDeployment);

        $activeDeployments = $queue->getActiveDeployments();

        $this->assertCount(1, $activeDeployments);
        $this->assertSame($secondDeployment, $activeDeployments[0]);
    }

    #[Test]
    public function itShouldReturnPendingDeployments(): void
    {
        $firstDeployment = $this->createMock(Deployment::class);
        $firstDeployment->expects($this->once())
            ->method('isPending')
            ->willReturn(true);

        $secondDeployment = $this->createMock(Deployment::class);
        $secondDeployment->expects($this->once())
            ->method('isPending')
            ->willReturn(false);

        $queue = new DeploymentQueue();
        $queue->addQueuedUser($firstDeployment)->addQueuedUser($secondDeployment);

        $pendingDeployments = $queue->getPendingDeployments();

        $this->assertCount(1, $pendingDeployments);
        $this->assertSame($firstDeployment, $pendingDeployments[0]);
    }
}
