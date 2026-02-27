<?php

declare(strict_types=1);

namespace App\Service\Queue\Join\Handler;

use App\Entity\Deployment;
use App\Enum\DeploymentUser;
use App\Factory\DeploymentUserFactory;
use App\Service\Queue\Context\QueueContextInterface;
use App\Service\Queue\Join\JoinQueueContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 4_100)]
readonly class AddNotifyUsersToDeploymentHandler implements JoinQueueHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private DeploymentUserFactory $deploymentUserFactory,
    ) {
    }

    public function supports(QueueContextInterface $context): bool
    {
        return $context instanceof JoinQueueContext
            && $context->getQueuedUser() instanceof Deployment
            && !$context->getNotifyUsers()->isEmpty();
    }

    public function handle(QueueContextInterface $context): void
    {
        if (!$context instanceof JoinQueueContext) {
            return;
        }

        $this->logger->debug('Adding users to notify to deployment on {queue} {contextId} {contextType}', [
            'queue' => $context->getQueue()->getId(),
            'contextId' => $context->getId(),
            'contextType' => $context->getType()->value,
        ]);

        /** @var Deployment $deployment */
        $deployment = $context->getQueuedUser();

        foreach ($context->getNotifyUsers() as $user) {
            $deployment->addUser(
                $this->deploymentUserFactory->create($user, $deployment, DeploymentUser::NOTIFY)
            );
        }
    }
}
