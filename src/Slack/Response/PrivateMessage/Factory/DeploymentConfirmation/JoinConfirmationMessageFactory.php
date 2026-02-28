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

readonly class JoinConfirmationMessageFactory
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
                'Confirmation required to join `%s` to deploy `%s` to `%s`.',
                $queueName = $queue->getName(),
                $description = $deployment->getDescription(),
                $repositoryName = $deployment->getRepository()?->getName(),
            ),
            [
                new SectionBlock(
                    sprintf(
                        '%s has joined the `%s` queue to deploy `%s` to `%s`. Please confirm you will be ready to start the deployment and make any checks when the deployment is completed.',
                        $deployment->getUserLink(),
                        $queueName,
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
