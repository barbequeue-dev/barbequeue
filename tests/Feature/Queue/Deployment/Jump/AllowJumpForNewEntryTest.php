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

class AllowJumpForNewEntryTest extends FeatureTestCase
{
    #[Test]
    public function itShouldAllowJumpForNewEntryOnJumpQueue(): void
    {
        $this->createRepository($firstRepository = 'first-repository')
            ->createRepository($secondRepository = 'second-repository')

            ->createDeploymentQueue($blockerQueue = 'blocker-queue', [$firstRepository, $secondRepository], QueueBehaviour::ENFORCE_QUEUE)
            ->createDeploymentQueue($blockedQueue = 'blocked-queue', [$firstRepository, $secondRepository], QueueBehaviour::ALLOW_JUMPS)

            ->joinDeploymentQueue($blockerQueue, $firstRepository, $blockerDescription = 'blocker description', $link = 'https://example.com')
            ->assertDeploymentExists($blockerQueue, $firstRepository, $blockerDescription, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `first-repository` now!')

            ->joinDeploymentQueue($blockedQueue, $firstRepository, $blockedDescription = 'blocked description', $link)
            ->assertDeploymentExists($blockedQueue, $firstRepository, $blockedDescription, $link, pending: true)
            ->assertInteractionResponseSentContainingMessage('You are now 1st in the `blocked-queue` queue.', true)
            ->assertInteractionResponseSentContainingMessage('You will have to wait for <@test> in the `blocker-queue` queue to finish')

            ->joinDeploymentQueue($blockedQueue, $secondRepository, $description = 'should jump blocked deployment', $link)
            ->assertDeploymentExists($blockedQueue, $secondRepository, $description, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `second-repository` now!')

            ->sendUserCommand(SubCommand::LEAVE, [$blockerQueue])
            ->assertInteractionResponseSentContainingMessage('You have left the `blocker-queue` queue.')

            ->assertDeploymentExists($blockedQueue, $firstRepository, $blockedDescription, $link, pending: true)
            ->assertDeploymentExists($blockedQueue, $secondRepository, $description, $link, active: true)

            ->sendUserCommand(SubCommand::LEAVE, [$blockedQueue])
            ->assertModalOpened(Modal::LEAVE_QUEUE)
            ->sendViewSubmission(
                Interaction::LEAVE_QUEUE,
                [
                    $this->getSingleSelectArgument(ModalArgument::QUEUED_USER_ID, (string) $this->getDeploymentId($blockedQueue, $description)),
                ],
                [
                    'queue' => $blockedQueue,
                ],
            )
            ->assertInteractionResponseSentContainingMessage('You have been removed from the `blocked-queue` queue.')
            ->assertPrivateMessageSentContainingMessage('You can start deploying `blocked description` to `first-repository` now!')
        ;
    }
}
