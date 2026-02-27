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

class NoBlockerSimultaneousTest extends FeatureTestCase
{
    #[Test]
    public function itShouldAllowDeployment(): void
    {
        // Create some repositories
        $this->createRepository($firstRepository = 'repository-1')
            ->createRepository($secondRepository = 'repository-2', 'repositoryUrl')

            // Create a deployment queue with the two existing repositories
            ->assertQueueNotExists($queueName = 'test')
            ->sendAdminCommand(SubCommand::ADD_QUEUE)
            ->assertModalOpened(Modal::ADD_QUEUE)
            ->sendInputSelectionInteraction(
                Interaction::QUEUE_TYPE,
                Queue::DEPLOYMENT->value,
                $viewId = 'deployment-queue-modal-view',
                'administrator',
            )
            ->assertModalUpdated(Modal::ADD_QUEUE_DEPLOYMENT, $viewId)
            ->sendViewSubmission(
                Interaction::ADD_DEPLOYMENT_QUEUE,
                [
                    $this->getPlainTextArgument(ModalArgument::QUEUE_NAME, BlockElement::PLAIN_TEXT_INPUT, $queueName),
                    $this->getSingleSelectArgument(ModalArgument::QUEUE_TYPE, Queue::DEPLOYMENT->value),
                    $this->getSingleSelectArgument(ModalArgument::QUEUE_BEHAVIOUR, QueueBehaviour::ALLOW_SIMULTANEOUS->value),
                    $this->getMultiStaticSelectArgument(ModalArgument::QUEUE_REPOSITORIES, [
                        $this->getRepositoryId($firstRepository),
                        $this->getRepositoryId($secondRepository),
                    ]),
                ],
                userId: 'administrator'
            )
            ->assertInteractionResponseSentContainingMessage('A deployment queue called `test` has been created!')
            ->assertDeploymentQueueExists($queueName, QueueBehaviour::ALLOW_SIMULTANEOUS, [$firstRepository, $secondRepository])

            // Join the new queue with the first repository - should be active
            ->joinDeploymentQueue($queueName, $firstRepository, $description = 'description', $link = 'https://example.com')
            ->assertDeploymentExists($queueName, $firstRepository, $description, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `repository-1` now!')

            // Join the queue with the first repository as a different user - should be blocked
            ->joinDeploymentQueue($queueName, $firstRepository, $description = 'secondDescription', $link = 'https://example2.com', $secondUser = 'test2')
            ->assertDeploymentExists($queueName, $firstRepository, $description, $link, pending: true, userId: $secondUser)
            ->assertInteractionResponseSentContainingMessage('You are now 2nd in the `test` queue. You will have to wait for <@test>')

            // Join the queue with the second repository as the second user - should be active
            ->joinDeploymentQueue($queueName, $secondRepository, $description = 'thirdDescription', $link = 'https://example3.com', $secondUser)
            ->assertDeploymentExists($queueName, $secondRepository, $description, $link, active: true, userId: $secondUser)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `repository-2` now!')

            // Join the queue with the second repository as the first user - should be blocked
            ->joinDeploymentQueue($queueName, $secondRepository, $description = 'fourthDescription', $link = 'https://example4.com')
            ->assertDeploymentExists($queueName, $secondRepository, $description, $link, pending: true)
            ->assertInteractionResponseSentContainingMessage('You are now 1st and 4th in the `test` queue. You will have to wait for <@test2>')

            // Finish the third deployment added to the queue (that was blocking the fourth one)
            ->sendUserCommand(SubCommand::LEAVE, [$queueName])
            ->assertModalOpened(Modal::LEAVE_QUEUE)
            ->sendViewSubmission(
                Interaction::LEAVE_QUEUE,
                [
                    $this->getSingleSelectArgument(ModalArgument::QUEUED_USER_ID, (string) $this->getDeploymentId($queueName, 'thirdDescription')),
                ],
                [
                    'queue' => $queueName,
                ],
                $secondUser,
            )
            ->assertInteractionResponseSentContainingMessage('You have been removed from the `test` queue.')

            // Fourth deployment added should now be unblocked and deployment should have started
            ->assertPrivateMessageSentContainingMessage('You can start deploying `fourthDescription` to `repository-2` now!')
            ->assertQueuedUserPositionsInQueueCorrect($queueName, [3], $secondUser)
            ->assertQueuedUserPositionsInQueueCorrect($queueName, [1, 2])
            ->assertDeploymentExists($queueName, $secondRepository, $description, $link, active: true)

            // Create a new repository
            ->createRepository($thirdRepository = 'repository-3')

            // Add it to the queue
            ->sendAdminCommand(SubCommand::EDIT_QUEUE, [$queueName])
            ->assertModalOpened(Modal::EDIT_QUEUE_DEPLOYMENT)
            ->sendViewSubmission(
                Interaction::EDIT_QUEUE_DEPLOYMENT,
                [
                    $this->getSingleSelectArgument(ModalArgument::QUEUE_BEHAVIOUR, QueueBehaviour::ALLOW_SIMULTANEOUS->value),
                    $this->getMultiStaticSelectArgument(ModalArgument::QUEUE_REPOSITORIES, [
                        $this->getRepositoryId($firstRepository),
                        $this->getRepositoryId($secondRepository),
                        $this->getRepositoryId($thirdRepository),
                    ]),
                ],
                [
                    'queue' => $this->getQueueId($queueName),
                ],
                'administrator',
            )
            ->assertInteractionResponseSentContainingMessage('Queue `test` edited successfully.', true)
            ->assertInteractionResponseSentContainingMessage('repository-1, repository-2, repository-3')

            // Join the queue again with the third repository, should allow immediate deployment
            ->joinDeploymentQueue($queueName, $thirdRepository, $description = 'shouldDeployImmediately', $link = 'https://example.com')
            ->assertDeploymentExists($queueName, $thirdRepository, $description, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `repository-3` now!')
        ;
    }
}
