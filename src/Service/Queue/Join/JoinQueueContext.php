<?php

declare(strict_types=1);

namespace App\Service\Queue\Join;

use App\Entity\QueuedUser;
use App\Entity\Repository;
use App\Entity\User;
use App\Service\Queue\Context\ContextType;
use App\Service\Queue\Context\QueueContext;
use App\Service\Queue\Context\QueueContextInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class JoinQueueContext extends QueueContext implements QueueContextInterface
{
    private QueuedUser $queuedUser;

    private ?Repository $repository = null;

    /** @var Collection<int, User> */
    private Collection $notifyUsers;

    public function __construct(
        string $queueName,
        string $teamId,
        string $userId,
        private readonly string $userName,
        private readonly ?int $requiredMinutes = null,
        private readonly ?string $deploymentDescription = null,
        private ?string $deploymentLink = null,
        private readonly ?int $deploymentRepositoryId = null,
        /** @var string[] $notifyUserIds */
        private readonly array $notifyUserIds = [],
    ) {
        parent::__construct($queueName, $teamId, $userId);

        $this->notifyUsers = new ArrayCollection();
    }

    public function getType(): ContextType
    {
        return ContextType::JOIN;
    }

    public function getRequiredMinutes(): ?int
    {
        return $this->requiredMinutes;
    }

    public function getDeploymentDescription(): ?string
    {
        return $this->deploymentDescription;
    }

    public function getDeploymentLink(): ?string
    {
        return $this->deploymentLink;
    }

    public function setDeploymentLink(?string $deploymentLink): void
    {
        $this->deploymentLink = $deploymentLink;
    }

    public function getDeploymentRepositoryId(): ?int
    {
        return $this->deploymentRepositoryId;
    }

    /** @return string[] */
    public function getNotifyUserIds(): array
    {
        return $this->notifyUserIds;
    }

    public function getQueuedUser(): QueuedUser
    {
        return $this->queuedUser;
    }

    public function setQueuedUser(QueuedUser $queuedUser): void
    {
        $this->queuedUser = $queuedUser;
    }

    public function getRepository(): ?Repository
    {
        return $this->repository;
    }

    public function setRepository(?Repository $repository): void
    {
        $this->repository = $repository;
    }

    /** @return Collection<int, User> */
    public function getNotifyUsers(): Collection
    {
        return $this->notifyUsers;
    }

    public function addNotifyUser(User $user): void
    {
        if (!$this->notifyUsers->contains($user)) {
            $this->notifyUsers->add($user);
        }
    }

    public function getUserName(): string
    {
        return $this->userName;
    }
}
