<?php

declare(strict_types=1);

namespace App\Tests\Unit\Slack\Interaction\Handler\Queue\Join;

use App\Entity\Queue;
use App\Entity\QueuedUser;
use App\Service\Queue\Exception\DeploymentInformationRequiredException;
use App\Service\Queue\Exception\InvalidDeploymentUrlException;
use App\Service\Queue\Exception\QueueNotFoundException;
use App\Service\Queue\Exception\UnableToJoinQueueException;
use App\Service\Queue\Join\JoinQueueContext;
use App\Service\Queue\Join\JoinQueueHandler;
use App\Slack\Interaction\Handler\Queue\Join\JoinDeploymentQueueInteractionHandler;
use App\Slack\Interaction\Handler\SlackInteractionHandlerInterface;
use App\Slack\Interaction\Interaction;
use App\Slack\Interaction\InteractionType;
use App\Slack\Interaction\SlackInteraction;
use App\Slack\Interaction\SlackViewSubmission;
use App\Slack\Response\Interaction\Factory\Queue\Join\InvalidDeploymentUrlResponseFactory;
use App\Slack\Response\Interaction\Factory\Queue\Join\QueueJoinedResponseFactory;
use App\Slack\Response\Interaction\Factory\Queue\Join\UnableToJoinQueueResponseFactory;
use App\Slack\Response\Interaction\SlackInteractionResponse;
use App\Slack\Response\PrivateMessage\NoResponse;
use App\Tests\Unit\Slack\Interaction\Handler\AbstractInteractionHandlerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

#[CoversClass(JoinDeploymentQueueInteractionHandler::class)]
class JoinDeploymentQueueInteractionHandlerTest extends AbstractInteractionHandlerTestCase
{
    #[Test]
    public function itShouldReturnEarlyIfNotSlackViewSubmission(): void
    {
        $this->expectNotToPerformAssertions();

        $handler = $this->getHandler();

        $handler->handle($this->createStub(SlackInteraction::class));
    }

    #[Test]
    public function itShouldLogWarningIfQueueNotFoundExceptionThrown(): void
    {
        $queueName = 'queueName';
        $deploymentDescription = 'deploymentDescription';
        $deploymentLink = 'deploymentLink';
        $requiredMinutes = 30;
        $repositoryId = 1;

        $argumentStringCallCount = 0;
        $interaction = $this->createMock(SlackViewSubmission::class);
        $interaction->expects($this->exactly(3))
            ->method('getArgumentString')
            ->willReturnCallback(function ($argument) use ($queueName, $deploymentDescription, $deploymentLink, &$argumentStringCallCount) {
                if (1 === ++$argumentStringCallCount) {
                    $this->assertEquals('join_queue_name', $argument);

                    return $queueName;
                }

                if (2 === $argumentStringCallCount) {
                    $this->assertEquals('deployment_description', $argument);

                    return $deploymentDescription;
                }

                $this->assertEquals('deployment_link', $argument);

                return $deploymentLink;
            });

        $argumentIntegerCallCount = 0;
        $interaction->expects($this->exactly(2))
            ->method('getArgumentInteger')
            ->willReturnCallback(function ($argument) use ($requiredMinutes, $repositoryId, &$argumentIntegerCallCount) {
                if (1 === ++$argumentIntegerCallCount) {
                    $this->assertEquals('join_queue_required_minutes', $argument);

                    return $requiredMinutes;
                }

                $this->assertEquals('deployment_repository', $argument);

                return $repositoryId;
            });

        $interaction->expects($this->once())
            ->method('getArgumentStringArray')
            ->with('deployment_notify_users')
            ->willReturn($notifyUsers = ['userId']);

        $interaction->expects($this->once())
            ->method('getTeamId')
            ->willReturn($teamId = 'teamId');

        $interaction->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId = 'userId');

        $interaction->expects($this->once())
            ->method('setResponse')
            ->willReturnCallback(function ($response) {
                $this->assertInstanceOf(NoResponse::class, $response);
            });

        $interaction->expects($this->once())
            ->method('setHandled');

        $handler = $this->createMock(JoinQueueHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function ($context) use ($queueName, $teamId, $userId, $requiredMinutes, $deploymentDescription, $deploymentLink, $repositoryId, $notifyUsers) {
                $this->assertInstanceOf(JoinQueueContext::class, $context);
                $this->assertSame($queueName, $context->getQueueIdentifier());
                $this->assertSame($teamId, $context->getTeamId());
                $this->assertSame($userId, $context->getUserId());
                $this->assertSame($requiredMinutes, $context->getRequiredMinutes());
                $this->assertSame($deploymentDescription, $context->getDeploymentDescription());
                $this->assertSame($deploymentLink, $context->getDeploymentLink());
                $this->assertSame($repositoryId, $context->getDeploymentRepositoryId());
                $this->assertSame($notifyUsers, $context->getNotifyUserIds());

                $exception = $this->createMock(QueueNotFoundException::class);
                $exception->expects($this->once())
                    ->method('getQueueName')
                    ->willReturn($queueName);

                throw $exception;
            });

        $this->expectsWarning('A queue called {queueName} could not be found when joining the queue.', [
            'queueName' => $queueName,
        ]);

        $queueJoinedResponseFactory = $this->createMock(QueueJoinedResponseFactory::class);
        $queueJoinedResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $unableToJoinQueueResponseFactory = $this->createMock(UnableToJoinQueueResponseFactory::class);
        $unableToJoinQueueResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $handler = new JoinDeploymentQueueInteractionHandler(
            $this->getLogger(),
            $queueJoinedResponseFactory,
            $unableToJoinQueueResponseFactory,
            $handler,
            $this->createStub(InvalidDeploymentUrlResponseFactory::class),
        );

        $handler->handle($interaction);
    }

    #[Test]
    public function itShouldLogWarningIfDeploymentInformationRequiredExceptionThrown(): void
    {
        $queueName = 'queueName';
        $deploymentDescription = 'deploymentDescription';
        $deploymentLink = 'deploymentLink';
        $requiredMinutes = 30;
        $repositoryId = 1;

        $argumentStringCallCount = 0;
        $interaction = $this->createMock(SlackViewSubmission::class);
        $interaction->expects($this->exactly(3))
            ->method('getArgumentString')
            ->willReturnCallback(function ($argument) use ($queueName, $deploymentDescription, $deploymentLink, &$argumentStringCallCount) {
                if (1 === ++$argumentStringCallCount) {
                    $this->assertEquals('join_queue_name', $argument);

                    return $queueName;
                }

                if (2 === $argumentStringCallCount) {
                    $this->assertEquals('deployment_description', $argument);

                    return $deploymentDescription;
                }

                $this->assertEquals('deployment_link', $argument);

                return $deploymentLink;
            });

        $argumentIntegerCallCount = 0;
        $interaction->expects($this->exactly(2))
            ->method('getArgumentInteger')
            ->willReturnCallback(function ($argument) use ($requiredMinutes, $repositoryId, &$argumentIntegerCallCount) {
                if (1 === ++$argumentIntegerCallCount) {
                    $this->assertEquals('join_queue_required_minutes', $argument);

                    return $requiredMinutes;
                }

                $this->assertEquals('deployment_repository', $argument);

                return $repositoryId;
            });

        $interaction->expects($this->once())
            ->method('getArgumentStringArray')
            ->with('deployment_notify_users')
            ->willReturn($notifyUsers = ['userId']);

        $interaction->expects($this->once())
            ->method('getTeamId')
            ->willReturn($teamId = 'teamId');

        $interaction->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId = 'userId');

        $interaction->expects($this->once())
            ->method('setResponse')
            ->willReturnCallback(function ($response) {
                $this->assertInstanceOf(NoResponse::class, $response);
            });

        $interaction->expects($this->once())
            ->method('setHandled');

        $handler = $this->createMock(JoinQueueHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function ($context) use ($queueName, $teamId, $userId, $requiredMinutes, $deploymentDescription, $deploymentLink, $repositoryId, $notifyUsers) {
                $this->assertInstanceOf(JoinQueueContext::class, $context);
                $this->assertSame($queueName, $context->getQueueIdentifier());
                $this->assertSame($teamId, $context->getTeamId());
                $this->assertSame($userId, $context->getUserId());
                $this->assertSame($requiredMinutes, $context->getRequiredMinutes());
                $this->assertSame($deploymentDescription, $context->getDeploymentDescription());
                $this->assertSame($deploymentLink, $context->getDeploymentLink());
                $this->assertSame($repositoryId, $context->getDeploymentRepositoryId());
                $this->assertSame($notifyUsers, $context->getNotifyUserIds());

                throw $this->createStub(DeploymentInformationRequiredException::class);
            });

        $this->expectsWarning('DeploymentInformationRequiredException thrown after submitting join deployment queue modal');

        $queueJoinedResponseFactory = $this->createMock(QueueJoinedResponseFactory::class);
        $queueJoinedResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $unableToJoinQueueResponseFactory = $this->createMock(UnableToJoinQueueResponseFactory::class);
        $unableToJoinQueueResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $handler = new JoinDeploymentQueueInteractionHandler(
            $this->getLogger(),
            $queueJoinedResponseFactory,
            $unableToJoinQueueResponseFactory,
            $handler,
            $this->createStub(InvalidDeploymentUrlResponseFactory::class),
        );

        $handler->handle($interaction);
    }

    #[Test]
    public function itShouldCreateUnableToJoinQueueResponseIfUnableToJoinQueueExceptionThrown(): void
    {
        $queueName = 'queueName';
        $deploymentDescription = 'deploymentDescription';
        $deploymentLink = 'deploymentLink';
        $requiredMinutes = 30;
        $repositoryId = 1;

        $argumentStringCallCount = 0;
        $interaction = $this->createMock(SlackViewSubmission::class);
        $interaction->expects($this->exactly(3))
            ->method('getArgumentString')
            ->willReturnCallback(function ($argument) use ($queueName, $deploymentDescription, $deploymentLink, &$argumentStringCallCount) {
                if (1 === ++$argumentStringCallCount) {
                    $this->assertEquals('join_queue_name', $argument);

                    return $queueName;
                }

                if (2 === $argumentStringCallCount) {
                    $this->assertEquals('deployment_description', $argument);

                    return $deploymentDescription;
                }

                $this->assertEquals('deployment_link', $argument);

                return $deploymentLink;
            });

        $argumentIntegerCallCount = 0;
        $interaction->expects($this->exactly(2))
            ->method('getArgumentInteger')
            ->willReturnCallback(function ($argument) use ($requiredMinutes, $repositoryId, &$argumentIntegerCallCount) {
                if (1 === ++$argumentIntegerCallCount) {
                    $this->assertEquals('join_queue_required_minutes', $argument);

                    return $requiredMinutes;
                }

                $this->assertEquals('deployment_repository', $argument);

                return $repositoryId;
            });

        $interaction->expects($this->once())
            ->method('getArgumentStringArray')
            ->with('deployment_notify_users')
            ->willReturn($notifyUsers = ['userId']);

        $interaction->expects($this->once())
            ->method('getTeamId')
            ->willReturn($teamId = 'teamId');

        $interaction->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId = 'userId');

        $interaction->expects($this->once())
            ->method('setResponse')
            ->with($response = $this->createStub(SlackInteractionResponse::class));

        $interaction->expects($this->once())
            ->method('setHandled');

        $queue = $this->createStub(Queue::class);

        $handler = $this->createMock(JoinQueueHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function ($context) use ($queue, $queueName, $teamId, $userId, $requiredMinutes, $deploymentDescription, $deploymentLink, $repositoryId, $notifyUsers) {
                $this->assertInstanceOf(JoinQueueContext::class, $context);
                $this->assertSame($queueName, $context->getQueueIdentifier());
                $this->assertSame($teamId, $context->getTeamId());
                $this->assertSame($userId, $context->getUserId());
                $this->assertSame($requiredMinutes, $context->getRequiredMinutes());
                $this->assertSame($deploymentDescription, $context->getDeploymentDescription());
                $this->assertSame($deploymentLink, $context->getDeploymentLink());
                $this->assertSame($repositoryId, $context->getDeploymentRepositoryId());
                $this->assertSame($notifyUsers, $context->getNotifyUserIds());

                $exception = $this->createMock(UnableToJoinQueueException::class);
                $exception->expects($this->once())
                    ->method('getQueue')
                    ->willReturn($queue);

                throw $exception;
            });

        $queueJoinedResponseFactory = $this->createMock(QueueJoinedResponseFactory::class);
        $queueJoinedResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $unableToJoinQueueResponseFactory = $this->createMock(UnableToJoinQueueResponseFactory::class);
        $unableToJoinQueueResponseFactory->expects($this->once())
            ->method('create')
            ->with($queue)
            ->willReturn($response);

        $handler = new JoinDeploymentQueueInteractionHandler(
            $this->getLogger(),
            $queueJoinedResponseFactory,
            $unableToJoinQueueResponseFactory,
            $handler,
            $this->createStub(InvalidDeploymentUrlResponseFactory::class),
        );

        $handler->handle($interaction);
    }

    #[Test]
    public function itShouldCreateInvalidDeploymentUrlResponseIfInvalidDeploymentUrlExceptionThrown(): void
    {
        $queueName = 'queueName';
        $deploymentDescription = 'deploymentDescription';
        $deploymentLink = 'deploymentLink';
        $requiredMinutes = 30;
        $repositoryId = 1;

        $argumentStringCallCount = 0;
        $interaction = $this->createMock(SlackViewSubmission::class);
        $interaction->expects($this->exactly(3))
            ->method('getArgumentString')
            ->willReturnCallback(function ($argument) use ($queueName, $deploymentDescription, $deploymentLink, &$argumentStringCallCount) {
                if (1 === ++$argumentStringCallCount) {
                    $this->assertEquals('join_queue_name', $argument);

                    return $queueName;
                }

                if (2 === $argumentStringCallCount) {
                    $this->assertEquals('deployment_description', $argument);

                    return $deploymentDescription;
                }

                $this->assertEquals('deployment_link', $argument);

                return $deploymentLink;
            });

        $argumentIntegerCallCount = 0;
        $interaction->expects($this->exactly(2))
            ->method('getArgumentInteger')
            ->willReturnCallback(function ($argument) use ($requiredMinutes, $repositoryId, &$argumentIntegerCallCount) {
                if (1 === ++$argumentIntegerCallCount) {
                    $this->assertEquals('join_queue_required_minutes', $argument);

                    return $requiredMinutes;
                }

                $this->assertEquals('deployment_repository', $argument);

                return $repositoryId;
            });

        $interaction->expects($this->once())
            ->method('getArgumentStringArray')
            ->with('deployment_notify_users')
            ->willReturn($notifyUsers = ['userId']);

        $interaction->expects($this->once())
            ->method('getTeamId')
            ->willReturn($teamId = 'teamId');

        $interaction->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId = 'userId');

        $interaction->expects($this->once())
            ->method('setResponse')
            ->with($response = $this->createStub(SlackInteractionResponse::class));

        $interaction->expects($this->once())
            ->method('setHandled');

        $queue = $this->createStub(Queue::class);

        $handler = $this->createMock(JoinQueueHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function ($context) use ($queue, $queueName, $teamId, $userId, $requiredMinutes, $deploymentDescription, $deploymentLink, $repositoryId, $notifyUsers) {
                $this->assertInstanceOf(JoinQueueContext::class, $context);
                $this->assertSame($queueName, $context->getQueueIdentifier());
                $this->assertSame($teamId, $context->getTeamId());
                $this->assertSame($userId, $context->getUserId());
                $this->assertSame($requiredMinutes, $context->getRequiredMinutes());
                $this->assertSame($deploymentDescription, $context->getDeploymentDescription());
                $this->assertSame($deploymentLink, $context->getDeploymentLink());
                $this->assertSame($repositoryId, $context->getDeploymentRepositoryId());
                $this->assertSame($notifyUsers, $context->getNotifyUserIds());

                $exception = $this->createMock(InvalidDeploymentUrlException::class);
                $exception->expects($this->once())
                    ->method('getQueue')
                    ->willReturn($queue);

                $exception->expects($this->once())
                    ->method('getDeploymentLink')
                    ->willReturn($deploymentLink);

                throw $exception;
            });

        $queueJoinedResponseFactory = $this->createMock(QueueJoinedResponseFactory::class);
        $queueJoinedResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $unableToJoinQueueResponseFactory = $this->createMock(UnableToJoinQueueResponseFactory::class);
        $unableToJoinQueueResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $invalidDeploymentUrlResponseFactory = $this->createMock(InvalidDeploymentUrlResponseFactory::class);
        $invalidDeploymentUrlResponseFactory->expects($this->once())
            ->method('create')
            ->with($deploymentLink, $queue)
            ->willReturn($response);

        $handler = new JoinDeploymentQueueInteractionHandler(
            $this->getLogger(),
            $queueJoinedResponseFactory,
            $unableToJoinQueueResponseFactory,
            $handler,
            $invalidDeploymentUrlResponseFactory,
        );

        $handler->handle($interaction);
    }

    #[Test]
    public function itShouldCreateQueueJoinedResponse(): void
    {
        $queueName = 'queueName';
        $deploymentDescription = 'deploymentDescription';
        $deploymentLink = 'deploymentLink';
        $requiredMinutes = 30;
        $repositoryId = 1;

        $argumentStringCallCount = 0;
        $interaction = $this->createMock(SlackViewSubmission::class);
        $interaction->expects($this->exactly(3))
            ->method('getArgumentString')
            ->willReturnCallback(function ($argument) use ($queueName, $deploymentDescription, $deploymentLink, &$argumentStringCallCount) {
                if (1 === ++$argumentStringCallCount) {
                    $this->assertEquals('join_queue_name', $argument);

                    return $queueName;
                }

                if (2 === $argumentStringCallCount) {
                    $this->assertEquals('deployment_description', $argument);

                    return $deploymentDescription;
                }

                $this->assertEquals('deployment_link', $argument);

                return $deploymentLink;
            });

        $argumentIntegerCallCount = 0;
        $interaction->expects($this->exactly(2))
            ->method('getArgumentInteger')
            ->willReturnCallback(function ($argument) use ($requiredMinutes, $repositoryId, &$argumentIntegerCallCount) {
                if (1 === ++$argumentIntegerCallCount) {
                    $this->assertEquals('join_queue_required_minutes', $argument);

                    return $requiredMinutes;
                }

                $this->assertEquals('deployment_repository', $argument);

                return $repositoryId;
            });

        $interaction->expects($this->once())
            ->method('getArgumentStringArray')
            ->with('deployment_notify_users')
            ->willReturn($notifyUsers = ['userId']);

        $interaction->expects($this->once())
            ->method('getTeamId')
            ->willReturn($teamId = 'teamId');

        $interaction->expects($this->once())
            ->method('getUserId')
            ->willReturn($userId = 'userId');

        $interaction->expects($this->once())
            ->method('setResponse')
            ->with($response = $this->createStub(SlackInteractionResponse::class));

        $interaction->expects($this->once())
            ->method('setHandled');

        $queuedUser = $this->createStub(QueuedUser::class);

        $handler = $this->createMock(JoinQueueHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function ($context) use ($queuedUser, $queueName, $teamId, $userId, $requiredMinutes, $deploymentDescription, $deploymentLink, $repositoryId, $notifyUsers) {
                $this->assertInstanceOf(JoinQueueContext::class, $context);
                $this->assertSame($queueName, $context->getQueueIdentifier());
                $this->assertSame($teamId, $context->getTeamId());
                $this->assertSame($userId, $context->getUserId());
                $this->assertSame($requiredMinutes, $context->getRequiredMinutes());
                $this->assertSame($deploymentDescription, $context->getDeploymentDescription());
                $this->assertSame($deploymentLink, $context->getDeploymentLink());
                $this->assertSame($repositoryId, $context->getDeploymentRepositoryId());
                $this->assertSame($notifyUsers, $context->getNotifyUserIds());

                $context->setQueuedUser($queuedUser);
            });

        $queueJoinedResponseFactory = $this->createMock(QueueJoinedResponseFactory::class);
        $queueJoinedResponseFactory->expects($this->once())
            ->method('create')
            ->with($queuedUser)
            ->willReturn($response);

        $unableToJoinQueueResponseFactory = $this->createMock(UnableToJoinQueueResponseFactory::class);
        $unableToJoinQueueResponseFactory->expects($this->never())
            ->method('create')
            ->withAnyParameters();

        $handler = new JoinDeploymentQueueInteractionHandler(
            $this->getLogger(),
            $queueJoinedResponseFactory,
            $unableToJoinQueueResponseFactory,
            $handler,
            $this->createStub(InvalidDeploymentUrlResponseFactory::class),
        );

        $handler->handle($interaction);
    }

    protected function getSupportedInteraction(): Interaction
    {
        return Interaction::JOIN_QUEUE_DEPLOYMENT;
    }

    protected function getUnsupportedInteraction(): Interaction
    {
        return Interaction::JOIN_QUEUE;
    }

    protected function getSupportedInteractionType(): InteractionType
    {
        return InteractionType::VIEW_SUBMISSION;
    }

    protected function getUnsupportedInteractionType(): InteractionType
    {
        return InteractionType::BLOCK_ACTIONS;
    }

    protected function getHandler(): SlackInteractionHandlerInterface
    {
        return new JoinDeploymentQueueInteractionHandler(
            $this->createStub(LoggerInterface::class),
            $this->createStub(QueueJoinedResponseFactory::class),
            $this->createStub(UnableToJoinQueueResponseFactory::class),
            $this->createStub(JoinQueueHandler::class),
            $this->createStub(InvalidDeploymentUrlResponseFactory::class),
        );
    }
}
