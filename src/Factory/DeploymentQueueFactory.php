<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\DeploymentQueue;
use App\Entity\DeploymentQueueSettings;
use App\Entity\Workspace;
use App\Enum\QueueBehaviour;
use App\Repository\RepositoryRepositoryInterface;

readonly class DeploymentQueueFactory
{
    public function __construct(
        private RepositoryRepositoryInterface $repositoryRepository,
    ) {
    }

    /** @param int[] $repositoryIds */
    public function create(
        string $name,
        ?Workspace $workspace,
        ?int $maximumEntriesPerUser,
        ?int $expiryMinutes,
        array $repositoryIds,
        string $behaviour,
    ): DeploymentQueue {
        $queue = new DeploymentQueue()
            ->setName($name)
            ->setWorkspace($workspace)
            ->setMaximumEntriesPerUser($maximumEntriesPerUser)
            ->setExpiryMinutes($expiryMinutes)
            ->setBehaviour(QueueBehaviour::tryFrom($behaviour) ?? QueueBehaviour::ENFORCE_QUEUE)
            ->setSettings(new DeploymentQueueSettings());

        if (null === $workspace) {
            return $queue;
        }

        $repositories = $this->repositoryRepository->findByIdsAndWorkspace($repositoryIds, $workspace);

        foreach ($repositories as $repository) {
            $queue->addRepository($repository);
        }

        return $queue;
    }
}
