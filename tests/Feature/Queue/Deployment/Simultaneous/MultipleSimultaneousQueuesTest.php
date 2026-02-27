<?php

declare(strict_types=1);

namespace App\Tests\Feature\Queue\Deployment\Simultaneous;

use App\Enum\Queue;
use App\Enum\QueueBehaviour;
use App\Slack\BlockElement\BlockElement;
use App\Slack\Command\SubCommand;
use App\Slack\Interaction\Interaction;
use App\Slack\Surface\Component\Modal;
use App\Slack\Surface\Component\ModalArgument;
use App\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

class MultipleSimultaneousQueuesTest extends FeatureTestCase
{
    #[Test]
    public function itShouldAllowDeploymentsOnRepositoriesWithNoActiveDeployment(): void
    {
        $this->createRepository($firstRepository = 'firstRepository')
            ->createRepository($secondRepository = 'secondRepository')

            ->sendAdminCommand(SubCommand::EDIT_REPOSITORY, [$firstRepository])
            ->assertModalOpened(Modal::EDIT_REPOSITORY)
            ->sendViewSubmission(
                Interaction::EDIT_REPOSITORY,
                [
                    $this->getPlainTextArgument(ModalArgument::REPOSITORY_NAME, BlockElement::PLAIN_TEXT_INPUT, $firstRepository),
                    $this->getMultiStaticSelectArgument(ModalArgument::REPOSITORY_BLOCKS, [
                        $this->getRepositoryId($secondRepository),
                    ]),
                ],
                [
                    'repository_id' => $this->getRepositoryId($firstRepository),
                ],
                'administrator',
            )
            ->assertInteractionResponseSentContainingMessage('Repository `firstRepository` edited successfully.')

            ->sendAdminCommand(SubCommand::ADD_QUEUE)
            ->assertModalOpened(Modal::ADD_QUEUE)
            ->sendInputSelectionInteraction(
                Interaction::QUEUE_TYPE,
                Queue::DEPLOYMENT->value,
                $viewId = 'add-deployment-queue-view',
                'administrator',
            )
            ->assertModalUpdated(Modal::ADD_QUEUE_DEPLOYMENT, $viewId)
            ->sendViewSubmission(
                Interaction::ADD_DEPLOYMENT_QUEUE,
                [
                    $this->getPlainTextArgument(ModalArgument::QUEUE_NAME, BlockElement::PLAIN_TEXT_INPUT, $firstQueue = 'firstQueue'),
                    $this->getSingleSelectArgument(ModalArgument::QUEUE_TYPE, Queue::DEPLOYMENT->value),
                    $this->getSingleSelectArgument(ModalArgument::QUEUE_BEHAVIOUR, QueueBehaviour::ALLOW_SIMULTANEOUS->value),
                    $this->getMultiStaticSelectArgument(ModalArgument::QUEUE_REPOSITORIES, [
                        $this->getRepositoryId($firstRepository),
                        $this->getRepositoryId($secondRepository),
                    ]),
                    $this->getNumberArgument(ModalArgument::QUEUE_EXPIRY_MINUTES, $expiry = 5),
                ],
                userId: 'administrator'
            )
            ->assertDeploymentQueueExists(
                $firstQueue,
                QueueBehaviour::ALLOW_SIMULTANEOUS,
                [$firstRepository, $secondRepository],
                expiryMinutes: $expiry,
            )
            ->assertInteractionResponseSentContainingMessage('A deployment queue called `firstQueue` has been created!')

            ->sendAdminCommand(SubCommand::ADD_QUEUE)
            ->assertModalOpened(Modal::ADD_QUEUE)
            ->sendInputSelectionInteraction(
                Interaction::QUEUE_TYPE,
                Queue::DEPLOYMENT->value,
                $viewId,
                'administrator',
            )
            ->assertModalUpdated(Modal::ADD_QUEUE_DEPLOYMENT, $viewId)
            ->sendViewSubmission(
                Interaction::ADD_DEPLOYMENT_QUEUE,
                [
                    $this->getPlainTextArgument(ModalArgument::QUEUE_NAME, BlockElement::PLAIN_TEXT_INPUT, $secondQueue = 'secondQueue'),
                    $this->getSingleSelectArgument(ModalArgument::QUEUE_TYPE, Queue::DEPLOYMENT->value),
                    $this->getSingleSelectArgument(ModalArgument::QUEUE_BEHAVIOUR, QueueBehaviour::ALLOW_SIMULTANEOUS->value),
                    $this->getMultiStaticSelectArgument(ModalArgument::QUEUE_REPOSITORIES, [
                        $this->getRepositoryId($firstRepository),
                        $this->getRepositoryId($secondRepository),
                    ]),
                    $this->getNumberArgument(ModalArgument::QUEUE_EXPIRY_MINUTES, $expiry),
                ],
                userId: 'administrator'
            )
            ->assertDeploymentQueueExists(
                $secondQueue,
                QueueBehaviour::ALLOW_SIMULTANEOUS,
                [$firstRepository, $secondRepository],
                expiryMinutes: $expiry,
            )
            ->assertInteractionResponseSentContainingMessage('A deployment queue called `secondQueue` has been created!')

            ->joinDeploymentQueue(
                $firstQueue,
                $firstRepository,
                $firstDescription = 'blocker deployment',
                $firstLink = 'https://example.com'
            )
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `firstRepository` now!')
            ->assertDeploymentExists(
                $firstQueue,
                $firstRepository,
                $firstDescription,
                $firstLink,
                active: true,
                expiryMinutes: $expiry,
                hasExpiry: true,
            )

            ->joinDeploymentQueue(
                $secondQueue,
                $secondRepository,
                $secondDescription = 'blocked deployment',
                $secondLink = 'https://example.com',
            )
            ->assertInteractionResponseSentContainingMessage('You are now 1st in the `secondQueue` queue.', true)
            ->assertInteractionResponseSentContainingMessage('You will have to wait for <@test> in the `firstQueue` queue', true)
            ->assertInteractionResponseSentContainingMessage('Users deploying to `secondRepository`', true)
            ->assertInteractionResponseSentContainingMessage('Blocked by <@test> in the `firstQueue` queue.')
            ->assertDeploymentExists(
                $secondQueue,
                $secondRepository,
                $secondDescription,
                $secondLink,
                pending: true,
                expiryMinutes: $expiry,
            )

            ->setQueuedUserExpired($firstQueue, description: $firstDescription)
            ->sendAutomaticPopQueuesMessage()
            ->assertPrivateMessageSentContainingMessage('Your deployment of `blocker deployment` to `firstRepository` has completed automatically after 5 minutes.')
            ->assertPrivateMessageSentContainingMessage('You can start deploying `blocked deployment` to `secondRepository` now!')
            ->assertDeploymentExists(
                $secondQueue,
                $secondRepository,
                $secondDescription,
                $secondLink,
                active: true,
                expiryMinutes: $expiry,
                hasExpiry: true,
            )
        ;
    }
}
