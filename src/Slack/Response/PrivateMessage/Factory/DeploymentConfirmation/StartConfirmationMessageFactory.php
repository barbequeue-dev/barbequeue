<?php

declare(strict_types=1);

namespace App\Slack\Response\PrivateMessage\Factory\DeploymentConfirmation;

use App\Entity\Deployment;
use App\Entity\DeploymentConfirmation;
use App\Entity\DeploymentQueue;
use App\Entity\DeploymentUser;
use App\Slack\Block\Component\SectionBlock;
use App\Slack\BlockElement\Component\ButtonBlockElement;
use App\Slack\Common\Component\SlackConfirmation;
use App\Slack\Common\Style;
use App\Slack\Response\PrivateMessage\SlackPrivateMessage;

readonly class StartConfirmationMessageFactory
{
    public function create(DeploymentConfirmation $confirmation): SlackPrivateMessage
    {
        /** @var DeploymentUser $deploymentUser */
        $deploymentUser = $confirmation->getDeploymentUser();

        /** @var Deployment $deployment */
        $deployment = $deploymentUser->getDeployment();

        /** @var DeploymentQueue $queue */
        $queue = $deployment->getQueue();

        return new SlackPrivateMessage(
            $deploymentUser->getUser(),
            $queue->getWorkspace(),
            sprintf(
                'Confirmation required to start the deployment of `%s` to `%s`.',
                $description = $deployment->getDescription(),
                $repositoryName = $deployment->getRepository()?->getName(),
            ),
            [
                new SectionBlock(
                    sprintf(
                        'Your confirmation is required to start the deployment by %s of `%s` to `%s`.',
                        $deployment->getUserLink(),
                        $description,
                        $repositoryName,
                    ),
                    accessory: new ButtonBlockElement(
                        'Confirm',
                        'confirm-deployment',
                        value: (string) $confirmation->getId(),
                        style: Style::PRIMARY,
                        confirm: new SlackConfirmation(
                            'Confirm deployment',
                            'Are you sure?',
                            'Confirm',
                            'Cancel',
                            Style::PRIMARY,
                        ),
                    ),
                ),
            ]
        );
    }
}
