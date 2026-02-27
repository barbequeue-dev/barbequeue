<?php

declare(strict_types=1);

namespace App\Slack\Response\PrivateMessage\Factory\Deployment;

use App\Entity\Deployment;
use App\Entity\DeploymentQueue;
use App\Entity\Workspace;
use App\Slack\Block\Component\SectionBlock;
use App\Slack\BlockElement\Component\ButtonBlockElement;
use App\Slack\Common\Style;
use App\Slack\Response\PrivateMessage\SlackPrivateMessage;

readonly class AwaitingDeploymentMessageFactory
{
    public function create(Deployment $deployment, Workspace $workspace): SlackPrivateMessage
    {
        /** @var DeploymentQueue $queue */
        $queue = $deployment->getQueue();

        return new SlackPrivateMessage(
            $deployment->getUser(),
            $workspace,
            '',
            [
                new SectionBlock(
                    sprintf(
                        'You can start deploying `%s` to `%s` now!',
                        $deployment->getDescription(),
                        $deployment->getRepository()?->getName(),
                    ),
                ),
                new SectionBlock(
                    sprintf(
                        'You have %d minutes to confirm the deployment has started. This can be done by typing `/bbq start %s` or by clicking on the confirmation button below.',
                        $queue->getStartConfirmationTimeoutMinutes(),
                        $queue->getName(),
                    ),
                    accessory: new ButtonBlockElement(
                        'Confirm',
                        'confirm-deployment-started',
                        value: (string) $deployment->getId(),
                        style: Style::PRIMARY,
                    )
                ),
            ]
        );
    }
}
