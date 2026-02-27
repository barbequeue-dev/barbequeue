<?php

declare(strict_types=1);

namespace App\Tests\Feature\Queue\Deployment\Jump;

use App\Enum\QueueBehaviour;
use App\Slack\Command\SubCommand;
use App\Slack\Interaction\Interaction;
use App\Slack\Surface\Component\Modal;
use App\Slack\Surface\Component\ModalArgument;
use App\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

class AllowJumpForExistingEntryIfUnblockedTest extends FeatureTestCase
{
    #[Test]
    public function itShouldAllowJumpForExistingBlockedEntryIfUnblocked(): void
    {
        $this->createRepository($firstRepository = 'first-repository')
            ->createRepository($secondRepository = 'second-repository')

            ->createDeploymentQueue($firstQueue = 'first-queue', [$firstRepository, $secondRepository], QueueBehaviour::ALLOW_SIMULTANEOUS)
            ->createDeploymentQueue($secondQueue = 'second-queue', [$firstRepository, $secondRepository], QueueBehaviour::ALLOW_JUMPS)

            ->joinDeploymentQueue($firstQueue, $firstRepository, $firstBlocker = 'first-blocker', $link = 'https://example.com')
            ->assertDeploymentExists($firstQueue, $firstRepository, $firstBlocker, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `first-repository` now!')

            ->joinDeploymentQueue($firstQueue, $secondRepository, $secondBlocker = 'second-blocker', $link)
            ->assertDeploymentExists($firstQueue, $secondRepository, $secondBlocker, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `second-repository` now!')

            ->joinDeploymentQueue($secondQueue, $firstRepository, $firstBlocked = 'first-blocked', $link)
            ->assertDeploymentExists($secondQueue, $firstRepository, $firstBlocked, $link, pending: true)
            ->assertInteractionResponseSentContainingMessage('You are now 1st in the `second-queue` queue. You will have to wait for <@test>')

            ->joinDeploymentQueue($secondQueue, $secondRepository, $secondBlocked = 'second-blocked', $link)
            ->assertDeploymentExists($secondQueue, $secondRepository, $secondBlocked, $link, pending: true)
            ->assertInteractionResponseSentContainingMessage('You are now 1st and 2nd in the `second-queue` queue. You will have to wait for <@test>')

            ->sendUserCommand(SubCommand::LEAVE, [$firstQueue])
            ->assertModalOpened(Modal::LEAVE_QUEUE)
            ->sendViewSubmission(
                Interaction::LEAVE_QUEUE,
                [
                    $this->getSingleSelectArgument(ModalArgument::QUEUED_USER_ID, (string) $this->getDeploymentId($firstQueue, $secondBlocker)),
                ],
                [
                    'queue' => $firstQueue,
                ]
            )
            ->assertInteractionResponseSentContainingMessage('You have been removed from the `first-queue` queue.', true)
            ->assertInteractionResponseSentContainingMessage('You are now 1st in the `first-queue` queue.')

            ->assertPrivateMessageSentContainingMessage('You can start deploying `second-blocked` to `second-repository` now!')
            ->assertDeploymentExists($secondQueue, $secondRepository, $secondBlocked, $link, active: true)

            ->sendUserCommand(SubCommand::LEAVE, [$firstQueue])
            ->assertInteractionResponseSentContainingMessage('You have left the `first-queue` queue.')

            ->assertDeploymentExists($secondQueue, $firstRepository, $firstBlocked, $link, pending: true)

            ->sendUserCommand(SubCommand::LEAVE, [$secondQueue])
            ->assertModalOpened(Modal::LEAVE_QUEUE)
            ->sendViewSubmission(
                Interaction::LEAVE_QUEUE,
                [
                    $this->getSingleSelectArgument(ModalArgument::QUEUED_USER_ID, (string) $this->getDeploymentId($secondQueue, $secondBlocked)),
                ],
                [
                    'queue' => $secondQueue,
                ],
            )
            ->assertInteractionResponseSentContainingMessage('You have been removed from the `second-queue` queue.')

            ->assertDeploymentExists($secondQueue, $firstRepository, $firstBlocked, $link, active: true)
            ->assertPrivateMessageSentContainingMessage('You can start deploying `first-blocked` to `first-repository` now!')
        ;
    }
}
