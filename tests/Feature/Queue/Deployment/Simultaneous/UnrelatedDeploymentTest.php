<?php

declare(strict_types=1);

namespace App\Tests\Feature\Queue\Deployment\Simultaneous;

use App\Enum\QueueBehaviour;
use App\Slack\BlockElement\BlockElement;
use App\Slack\Command\SubCommand;
use App\Slack\Interaction\Interaction;
use App\Slack\Surface\Component\Modal;
use App\Slack\Surface\Component\ModalArgument;
use App\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

class UnrelatedDeploymentTest extends FeatureTestCase
{
    #[Test]
    public function itShouldStartUnrelatedDeploymentOnRepositoryThatBlocksRepositoryWithBlockedDeployment(): void
    {
        $this->createRepository($firstBlockerRepository = 'firstBlocker')
            ->createRepository($blockedRepository = 'blockedRepository')
            ->createRepository($secondBlockerRepository = 'secondBlocker')
            ->sendAdminCommand(SubCommand::EDIT_REPOSITORY, [$firstBlockerRepository])
            ->assertModalOpened(Modal::EDIT_REPOSITORY)
            ->sendViewSubmission(Interaction::EDIT_REPOSITORY, [
                $this->getPlainTextArgument(ModalArgument::REPOSITORY_NAME, BlockElement::PLAIN_TEXT_INPUT, $firstBlockerRepository),
                $this->getMultiStaticSelectArgument(ModalArgument::REPOSITORY_BLOCKS, [
                    $this->getRepositoryId($blockedRepository),
                ]),
            ], [
                'repository_id' => $this->getRepositoryId($firstBlockerRepository),
            ], 'administrator')
            ->assertInteractionResponseSentContainingMessage('` edited successfully.')
            ->sendAdminCommand(SubCommand::EDIT_REPOSITORY, [$secondBlockerRepository])
            ->assertModalOpened(Modal::EDIT_REPOSITORY)
            ->sendViewSubmission(Interaction::EDIT_REPOSITORY, [
                $this->getPlainTextArgument(ModalArgument::REPOSITORY_NAME, BlockElement::PLAIN_TEXT_INPUT, $secondBlockerRepository),
                $this->getMultiStaticSelectArgument(ModalArgument::REPOSITORY_BLOCKS, [
                    $this->getRepositoryId($blockedRepository),
                ]),
            ], [
                'repository_id' => $this->getRepositoryId($secondBlockerRepository),
            ], 'administrator')
            ->assertInteractionResponseSentContainingMessage('` edited successfully.')
            ->createDeploymentQueue(
                $firstQueue = 'firstQueue',
                [$firstBlockerRepository, $secondBlockerRepository, $blockedRepository],
                QueueBehaviour::ALLOW_SIMULTANEOUS,
            )
            ->createDeploymentQueue(
                $blockedQueue = 'blockedQueue',
                [$blockedRepository, $firstBlockerRepository],
                QueueBehaviour::ALLOW_SIMULTANEOUS,
            )
            ->joinDeploymentQueue($firstQueue, $firstBlockerRepository, $description = 'firstDescription', $link = 'https://example.com')
            ->assertDeploymentExists($firstQueue, $firstBlockerRepository, $description, $link, active: true)

            ->joinDeploymentQueue($blockedQueue, $blockedRepository, $blockedDescription = 'blockedDescription', $link)
            ->assertDeploymentExists($blockedQueue, $blockedRepository, $blockedDescription, $link, pending: true)

            ->joinDeploymentQueue($firstQueue, $secondBlockerRepository, $description = 'secondDescription', $link)
            ->assertDeploymentExists($firstQueue, $secondBlockerRepository, $description, $link, active: true)
            ->assertDeploymentExists($blockedQueue, $blockedRepository, $blockedDescription, $link, pending: true);
    }
}
