<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Queue\Join\Factory;

use App\Entity\DeploymentQueue;
use App\Entity\Repository;
use App\Entity\User;
use App\Entity\Workspace;
use App\Form\QueuedUser\Data\DeploymentData;
use App\Form\QueuedUser\Data\QueuedUserData;
use App\Service\Queue\Join\Factory\JoinQueueContextFactory;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(JoinQueueContextFactory::class)]
class JoinQueueContextFactoryTest extends KernelTestCase
{
    #[Test]
    public function itShouldCreateJoinQueueContextFromDeploymentData(): void
    {
        $data = $this->createMock(DeploymentData::class);
        $data->expects($this->once())
            ->method('getUser')
            ->willReturn($user = $this->createMock(User::class));

        $user->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createMock(Workspace::class));

        $data->expects($this->once())
            ->method('getQueueName')
            ->willReturn($queueName = 'queueName');

        $workspace->expects($this->once())
            ->method('getSlackId')
            ->willReturn($workspaceSlackId = 'workspaceSlackId');

        $user->expects($this->once())
            ->method('getSlackId')
            ->willReturn($slackId = 'slackId');

        $user->expects($this->once())
            ->method('getName')
            ->willReturn($name = 'name');

        $data->expects($this->once())
            ->method('getExpiryMinutes')
            ->willReturn($expiry = 10);

        $data->expects($this->once())
            ->method('getDescription')
            ->willReturn($description = 'description');

        $data->expects($this->once())
            ->method('getLink')
            ->willReturn($link = 'link');

        $data->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository = $this->createMock(Repository::class));

        $repository->expects($this->once())
            ->method('getId')
            ->willReturn($repositoryId = 1);

        $data->expects($this->once())
            ->method('getNotifyUsers')
            ->willReturn(new ArrayCollection([$notifyUser = $this->createMock(User::class)]));

        $notifyUser->expects($this->once())
            ->method('getSlackId')
            ->willReturn($notifySlackId = 'notifySlackId');

        $data->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue = $this->createStub(DeploymentQueue::class));

        $factory = new JoinQueueContextFactory();

        $result = $factory->createFromFormData($data);

        $this->assertSame($queueName, $result->getQueueIdentifier());
        $this->assertSame($workspaceSlackId, $result->getTeamId());
        $this->assertSame($slackId, $result->getUserId());
        $this->assertSame($expiry, $result->getRequiredMinutes());
        $this->assertSame($description, $result->getDeploymentDescription());
        $this->assertSame($link, $result->getDeploymentLink());
        $this->assertSame($repositoryId, $result->getDeploymentRepositoryId());
        $this->assertEquals([$notifySlackId], $result->getNotifyUserIds());
        $this->assertTrue($result->getNotifyUsers()->contains($notifyUser));
        $this->assertSame($queue, $result->getQueue());
        $this->assertSame($workspace, $result->getWorkspace());
        $this->assertSame($repository, $result->getRepository());
    }

    #[Test]
    public function itShouldCreateJoinQueueContextFromQueuedUserData(): void
    {
        $data = $this->createMock(QueuedUserData::class);
        $data->expects($this->once())
            ->method('getUser')
            ->willReturn($user = $this->createMock(User::class));

        $user->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createMock(Workspace::class));

        $data->expects($this->once())
            ->method('getQueueName')
            ->willReturn($queueName = 'queueName');

        $workspace->expects($this->once())
            ->method('getSlackId')
            ->willReturn($workspaceSlackId = 'workspaceSlackId');

        $user->expects($this->once())
            ->method('getSlackId')
            ->willReturn($slackId = 'slackId');

        $user->expects($this->once())
            ->method('getName')
            ->willReturn($name = 'name');

        $data->expects($this->once())
            ->method('getExpiryMinutes')
            ->willReturn($expiry = 10);

        $data->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue = $this->createStub(DeploymentQueue::class));

        $factory = new JoinQueueContextFactory();

        $result = $factory->createFromFormData($data);

        $this->assertSame($queueName, $result->getQueueIdentifier());
        $this->assertSame($workspaceSlackId, $result->getTeamId());
        $this->assertSame($slackId, $result->getUserId());
        $this->assertSame($expiry, $result->getRequiredMinutes());
        $this->assertSame($queue, $result->getQueue());
        $this->assertSame($workspace, $result->getWorkspace());
        $this->assertSame(null, $result->getRepository());
    }
}
