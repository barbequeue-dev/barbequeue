<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\QueueBehaviour;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints\Count;

#[Entity]
#[UniqueConstraint(columns: ['name', 'workspace_id'])]
class DeploymentQueue extends Queue
{
    /** @var Collection<int, Repository> */
    #[ManyToMany(targetEntity: Repository::class, inversedBy: 'deployments')]
    #[JoinTable(name: 'deployment_queue_repository')]
    #[Groups(['queue'])]

    #[Count(min: 1)]
    #[OA\Property(
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/QueueRepository')
    )]
    private Collection $repositories;

    #[Column(type: Types::ENUM, enumType: QueueBehaviour::class)]
    #[Groups(['queue'])]
    private QueueBehaviour $behaviour = QueueBehaviour::ENFORCE_QUEUE;

    /**
     * @var Collection<int, Deployment>
     *
     * @phpstan-ignore-next-line
     */
    private Collection $queuedUsers;

    #[OneToOne(targetEntity: DeploymentQueueSettings::class, mappedBy: 'deploymentQueue', cascade: ['persist'])]
    private ?DeploymentQueueSettings $settings = null;

    public function __construct()
    {
        parent::__construct();

        $this->repositories = new ArrayCollection();
    }

    /** @return Collection<int, Repository> */
    public function getRepositories(): Collection
    {
        return $this->repositories;
    }

    public function addRepository(Repository $repository): static
    {
        if (!$this->repositories->contains($repository)) {
            $this->repositories->add($repository);
        }

        return $this;
    }

    public function removeRepository(Repository $repository): static
    {
        if ($this->repositories->contains($repository)) {
            $this->repositories->removeElement($repository);
        }

        return $this;
    }

    public function clearRepositories(): static
    {
        foreach ($this->repositories as $repository) {
            $this->removeRepository($repository);
        }

        return $this;
    }

    public function getRepositoryList(): string
    {
        return implode(', ', $this->repositories->map(function (Repository $repository) {
            return $repository->getName();
        })->toArray());
    }

    public function getPrettyRepositoryList(): string
    {
        return implode(', ', $this->repositories->map(function (Repository $repository) {
            return '`'.$repository->getName().'`';
        })->toArray());
    }

    public function getBehaviour(): QueueBehaviour
    {
        return $this->behaviour;
    }

    public function setBehaviour(QueueBehaviour $behaviour): static
    {
        $this->behaviour = $behaviour;

        return $this;
    }

    public function hasActiveDeployment(): bool
    {
        /** @var Collection<int, Deployment> $queuedUsers */
        $queuedUsers = $this->getQueuedUsers();

        return $queuedUsers->exists(function (int $key, Deployment $deployment) {
            return $deployment->isActive();
        });
    }

    public function isDeploymentAllowed(Deployment $deployment): bool
    {
        if ($deployment === $this->getFirstPlace()) {
            return true;
        }

        if (!$this->getQueuedUsers()->contains($deployment)) {
            return false;
        }

        if (QueueBehaviour::ENFORCE_QUEUE === $this->behaviour) {
            return false;
        }

        if (QueueBehaviour::ALLOW_JUMPS === $this->behaviour && $this->hasActiveDeployment()) {
            return false;
        }

        /** @var Deployment[] $deployments */
        $deployments = $this->getSortedUsers();

        foreach ($deployments as $queueDeployment) {
            if (QueueBehaviour::ALLOW_JUMPS === $this->behaviour && $queueDeployment->isBlockedByRepository()) {
                continue;
            }

            if (QueueBehaviour::ALLOW_JUMPS === $this->behaviour && $queueDeployment !== $deployment) {
                return false;
            }

            if (QueueBehaviour::ALLOW_JUMPS === $this->behaviour) {
                return true;
            }

            if ($queueDeployment->getRepository() !== $deployment->getRepository()) {
                continue;
            }

            if ($queueDeployment->getRepository() === $deployment->getRepository() && $queueDeployment !== $deployment) {
                return false;
            }

            return true;
        }

        return true;
    }

    // TODO: Use new sort algorithm to sort by new statuses
    public function getSortedUsers(): array
    {
        $deployments = [];

        $users = parent::getSortedUsers();

        /** @var Deployment $deployment */
        foreach ($users as $deployment) {
            if ($deployment->isActive()) {
                $deployments[] = $deployment;
            }
        }

        /** @var Deployment $deployment */
        foreach ($users as $deployment) {
            if (!in_array($deployment, $deployments, true)) {
                $deployments[] = $deployment;
            }
        }

        return $deployments;
    }

    /** @return Deployment[] */
    public function getActiveDeployments(): array
    {
        /** @var Deployment[] $deployments */
        $deployments = $this->getSortedUsers();

        return array_filter($deployments, function (Deployment $deployment) {
            return $deployment->isActive();
        });
    }

    /** @return Deployment[] */
    public function getPendingDeployments(): array
    {
        /** @var Deployment[] $deployments */
        $deployments = $this->getSortedUsers();

        return array_filter($deployments, function (Deployment $deployment) {
            return $deployment->isPending();
        });
    }

    public function getSettings(): ?DeploymentQueueSettings
    {
        return $this->settings;
    }

    public function setSettings(?DeploymentQueueSettings $settings): static
    {
        if ($this->settings !== $settings) {
            $this->settings = $settings;
            $settings?->setDeploymentQueue($this);
        }

        return $this;
    }
}
