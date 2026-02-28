<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Queue\Join;

use App\Entity\Queue;
use App\Entity\QueuedUser;
use App\Entity\Repository;
use App\Entity\User;
use App\Entity\Workspace;
use App\Service\Queue\Join\JoinQueueContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(JoinQueueContext::class)]
class JoinQueueContextTest extends KernelTestCase
{
    #[Test]
    public function itShouldReturnPassedParameters(): void
    {
        $context = new JoinQueueContext(
            $queueName = 'queueName',
            $teamId = 'teamId',
            $userId = 'userId',
            $userName = 'userName',
            $requiredMinutes = 1,
            $deploymentDescription = 'deployment description',
            $deploymentLink = 'deployment link',
            $deploymentRepositoryId = 1,
            $notifyUsers = ['notifyUserId'],
        );

        $this->assertFalse($context->hasWorkspace());
        $this->assertFalse($context->hasQueue());
        $this->assertFalse($context->hasUser());

        $this->assertSame($queueName, $context->getQueueIdentifier());
        $this->assertSame($teamId, $context->getTeamId());
        $this->assertSame($userId, $context->getUserId());
        $this->assertSame($userName, $context->getUserName());
        $this->assertSame($requiredMinutes, $context->getRequiredMinutes());
        $this->assertSame($deploymentDescription, $context->getDeploymentDescription());
        $this->assertSame($deploymentLink, $context->getDeploymentLink());
        $this->assertSame($deploymentRepositoryId, $context->getDeploymentRepositoryId());
        $this->assertSame($notifyUsers, $context->getNotifyUserIds());

        $this->assertEmpty($context->getNotifyUsers());

        $context->addNotifyUser($user = $this->createStub(User::class));

        $this->assertSame($user, $context->getNotifyUsers()->first());

        $context->setQueuedUser($queuedUser = $this->createStub(QueuedUser::class));
        $this->assertSame($queuedUser, $context->getQueuedUser());

        $context->setRepository($repository = $this->createStub(Repository::class));
        $this->assertSame($repository, $context->getRepository());

        $context->setDeploymentLink($deploymentLink = 'newDeploymentLink');
        $this->assertSame($deploymentLink, $context->getDeploymentLink());

        $context->setUser($user = $this->createStub(User::class));
        $this->assertSame($user, $context->getUser());
        $this->assertTrue($context->hasUser());

        $context->setQueue($queue = $this->createStub(Queue::class));
        $this->assertSame($queue, $context->getQueue());
        $this->assertTrue($context->hasQueue());

        $context->setWorkspace($workspace = $this->createStub(Workspace::class));
        $this->assertSame($workspace, $context->getWorkspace());
        $this->assertTrue($context->hasWorkspace());
    }
}
