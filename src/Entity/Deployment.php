<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeploymentStatus;
use App\Enum\QueueBehaviour;
use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[Entity]
#[HasLifecycleCallbacks]
class Deployment extends QueuedUser
{
    #[ManyToOne(targetEntity: Repository::class)]
    #[Groups(['queue', 'queued-user'])]
    #[OA\Property(ref: new Model(type: Repository::class, groups: ['queue'], name: 'QueueRepository'))]
    private ?Repository $repository = null;

    #[Column(type: Types::STRING, nullable: true)]
    #[Groups(['queue', 'repository', 'queued-user'])]
    private ?string $link = null;

    #[Column(type: Types::TEXT, nullable: true)]
    #[Groups(['queue', 'repository', 'queued-user'])]
    private ?string $description = null;

    /** @var Collection<int, User> $notifyUsers */
    #[ManyToMany(targetEntity: User::class, cascade: ['persist'])]
    #[JoinTable(name: 'deployment_notify_user')]
    #[Groups(['queued-user'])]
    private Collection $notifyUsers;

    #[Column(type: Types::ENUM, nullable: false, enumType: DeploymentStatus::class)]
    #[Groups(['queue', 'repository', 'queued-user'])]
    private DeploymentStatus $status = DeploymentStatus::PENDING;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $startedAt = null;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $completedAt = null;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $confirmedAt = null;

    public function __construct()
    {
        $this->notifyUsers = new ArrayCollection();
    }

    public function getRepository(): ?Repository
    {
        return $this->repository;
    }

    public function setRepository(?Repository $repository): static
    {
        $this->repository = $repository;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return Collection<int, User> */
    public function getNotifyUsers(): Collection
    {
        return $this->notifyUsers;
    }

    public function addNotifyUser(User $user): static
    {
        if (!$this->notifyUsers->contains($user)) {
            $this->notifyUsers->add($user);
        }

        return $this;
    }

    public function removeNotifyUser(User $user): static
    {
        if ($this->notifyUsers->contains($user)) {
            $this->notifyUsers->removeElement($user);
        }

        return $this;
    }

    public function isBlockedByRepository(): bool
    {
        return $this->repository?->isBlockedByDeployment() ?? false;
    }

    public function getStatus(): DeploymentStatus
    {
        return $this->status;
    }

    public function setStatus(DeploymentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return DeploymentStatus::ACTIVE === $this->status;
    }

    public function isAwaitingDeployment(): bool
    {
        return DeploymentStatus::AWAITING_DEPLOYMENT === $this->status;
    }

    public function isBlockedByQueue(): bool
    {
        return match ($this->getQueue()?->getBehaviour() ?? QueueBehaviour::ENFORCE_QUEUE) {
            QueueBehaviour::ENFORCE_QUEUE => !$this->isFirstInQueue(),
            QueueBehaviour::ALLOW_SIMULTANEOUS => false,
            QueueBehaviour::ALLOW_JUMPS => $this->isBlockedByAllowJumpQueue(),
        };
    }

    public function isFirstInQueue(): bool
    {
        return $this->getQueue()?->getFirstPlace() === $this;
    }

    public function isBlockedByAllowJumpQueue(): bool
    {
        /** @var DeploymentQueue $queue */
        $queue = $this->getQueue();

        if (QueueBehaviour::ALLOW_JUMPS !== $queue->getBehaviour()) {
            return false;
        }

        if ($queue->hasActiveDeployment()) {
            return true;
        }

        /** @var Deployment $deployment */
        foreach ($queue->getSortedUsers() as $deployment) {
            if ($deployment === $this) {
                return false;
            }

            if (!$deployment->isBlockedByRepository()) {
                return true;
            }
        }

        return false;
    }

    #[Groups(['repository', 'queued-user'])]
    #[MaxDepth(2)]
    public function getBlocker(): ?Deployment
    {
        if ($this->isActive()) {
            return null;
        }

        if ($this->isFirstInQueue()) {
            return $this->repository?->getBlockingDeployment();
        }

        if (QueueBehaviour::ALLOW_SIMULTANEOUS === $this->getQueue()?->getBehaviour()) {
            return $this->repository?->getBlockingDeployment();
        }

        /** @var Deployment|null $firstPlace */
        $firstPlace = $this->getQueue()?->getFirstPlace();

        return $firstPlace;
    }

    public function getPlacement(): string
    {
        $deployments = $this->getQueue()?->getSortedUsers();

        if (empty($deployments)) {
            return '';
        }

        $place = 0;

        foreach ($deployments as $deployment) {
            ++$place;

            if ($deployment === $this) {
                return $place.$this->getOrdinalSuffix($place);
            }
        }

        return $place.$this->getOrdinalSuffix($place);
    }

    private function getOrdinalSuffix(int $number): string
    {
        return match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    public function getStartedAt(): ?CarbonImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?CarbonImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?CarbonImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?CarbonImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getConfirmedAt(): ?CarbonImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?CarbonImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }
}
