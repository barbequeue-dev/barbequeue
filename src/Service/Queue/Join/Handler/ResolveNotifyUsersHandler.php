<?php

declare(strict_types=1);

namespace App\Service\Queue\Join\Handler;

use App\Entity\DeploymentQueue;
use App\Resolver\UserResolver;
use App\Service\Queue\Context\QueueContextInterface;
use App\Service\Queue\Join\JoinQueueContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 4_700)]
readonly class ResolveNotifyUsersHandler implements JoinQueueHandlerInterface
{
    public function __construct(
        private UserResolver $userResolver,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(QueueContextInterface $context): bool
    {
        return $context instanceof JoinQueueContext
            && !empty($context->getNotifyUserIds())
            && $context->getQueue() instanceof DeploymentQueue
            && $context->getNotifyUsers()->isEmpty();
    }

    public function handle(QueueContextInterface $context): void
    {
        if (!$context instanceof JoinQueueContext) {
            return;
        }

        $this->logger->debug('Resolving users to notify about deployment {queue} {contextId} {contextType}', [
            'queue' => $context->getQueue()->getId(),
            'contextId' => $context->getId(),
            'contextType' => $context->getType()->value,
        ]);

        $workspace = $context->getWorkspace();

        foreach ($context->getNotifyUserIds() as $userId) {
            $context->addNotifyUser(
                $this->userResolver->resolve($userId, $workspace),
            );
        }
    }
}
