<?php

declare(strict_types=1);

namespace App\Service\Queue\Join\Factory;

use App\Entity\User;
use App\Form\QueuedUser\Data\DeploymentData;
use App\Form\QueuedUser\Data\QueuedUserData;
use App\Service\Queue\Join\JoinQueueContext;

class JoinQueueContextFactory
{
    public function createFromFormData(QueuedUserData $data): JoinQueueContext
    {
        $workspace = ($user = $data->getUser())->getWorkspace();

        if ($data instanceof DeploymentData) {
            $context = new JoinQueueContext(
                $data->getQueueName(),
                (string) $workspace?->getSlackId(),
                (string) $user->getSlackId(),
                (string) $user->getName(),
                $data->getExpiryMinutes(),
                $data->getDescription(),
                $data->getLink(),
                ($repository = $data->getRepository())?->getId(),
                ($notifyUsers = $data->getNotifyUsers())->map(fn (User $user) => (string) $user->getSlackId())->toArray(),
            );

            $context->setRepository($repository);

            foreach ($notifyUsers as $notifyUser) {
                $context->addNotifyUser($notifyUser);
            }
        }

        $context ??= new JoinQueueContext(
            $data->getQueueName(),
            (string) $workspace?->getSlackId(),
            (string) $user->getSlackId(),
            (string) $user->getName(),
            $data->getExpiryMinutes(),
        );
        $context->setQueue($data->getQueue());
        $context->setUser($user);

        if (null !== $workspace) {
            $context->setWorkspace($workspace);
        }

        return $context;
    }
}
