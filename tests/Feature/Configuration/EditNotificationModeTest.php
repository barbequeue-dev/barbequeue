<?php

declare(strict_types=1);

namespace App\Tests\Feature\Configuration;

use App\Enum\NotificationMode;
use App\Enum\NotificationSetting;
use App\Enum\QueueBehaviour;
use App\Slack\Command\SubCommand;
use App\Slack\Interaction\Interaction;
use App\Slack\Surface\Component\Modal;
use App\Slack\Surface\Component\ModalArgument;
use App\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

class EditNotificationModeTest extends FeatureTestCase
{
    #[Test]
    public function itShouldSaveNotificationMode(): void
    {
        // Set up test data
        $this->createRepository($repository = 'repository')
            ->createDeploymentQueue($queue = 'queue', [$repository], QueueBehaviour::ENFORCE_QUEUE)
            ->joinDeploymentQueue($queue, $repository, $description = 'firstDescription', $link = 'https://link.com')
            ->assertDeploymentExists($queue, $repository, $description, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `repository` now!')

            // Switch notifications to ephemeral only
            ->sendUserCommand(SubCommand::CONFIGURE)
            ->assertModalOpened(Modal::CONFIGURATION)
            ->sendViewSubmission(Interaction::SAVE_CONFIGURATION, [
                $this->getSingleSelectArgument(ModalArgument::CONFIGURATION_NOTIFICATION_MODE, NotificationMode::ONLY_WHEN_ACTIVE->value),
                $this->getMultiStaticSelectArgument(
                    ModalArgument::CONFIGURATION_DEPLOYMENT_NOTIFICATIONS,
                    [
                        NotificationSetting::DEPLOYMENT_COMPLETED->value,
                    ]
                ),
            ])
            ->assertInteractionResponseSentContainingMessage('Your preferences have been saved.')

            // On pop, the user should receive an ephemeral message
            ->sendAdminCommand(SubCommand::POP_QUEUE, [$queue])
            ->assertInteractionResponseSentContainingMessage('Queue `queue` has been popped')
            ->assertEphemeralMessageSentContainingMessage('Your deployment of `firstDescription` to `repository` has been marked as completed.')

            // Switch notifications to always notify
            ->sendUserCommand(SubCommand::CONFIGURE)
            ->assertModalOpened(Modal::CONFIGURATION)
            ->sendViewSubmission(Interaction::SAVE_CONFIGURATION, [
                $this->getSingleSelectArgument(ModalArgument::CONFIGURATION_NOTIFICATION_MODE, NotificationMode::ALWAYS_NOTIFY->value),
                $this->getMultiStaticSelectArgument(
                    ModalArgument::CONFIGURATION_DEPLOYMENT_NOTIFICATIONS,
                    [
                        NotificationSetting::DEPLOYMENT_COMPLETED->value,
                    ]
                ),
            ])
            ->assertInteractionResponseSentContainingMessage('Your preferences have been saved.')

            // Join the queue again
            ->joinDeploymentQueue($queue, $repository, $description, $link)
            ->assertDeploymentExists($queue, $repository, $description, $link, active: true)
            ->assertInteractionResponseSentContainingMessage('You can start your deployment on `repository` now!')

            // On pop, the user should receive a persistent message
            ->sendAdminCommand(SubCommand::POP_QUEUE, [$queue])
            ->assertInteractionResponseSentContainingMessage('Queue `queue` has been popped')
            ->assertPrivateMessageSentContainingMessage('Your deployment of `firstDescription` to `repository` has been marked as completed.')
        ;
    }
}
