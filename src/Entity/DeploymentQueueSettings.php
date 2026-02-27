<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;

#[Entity]
class DeploymentQueueSettings
{
    #[Id]
    #[OneToOne(targetEntity: DeploymentQueue::class, inversedBy: 'settings')]
    #[JoinColumn(nullable: false)]
    private ?DeploymentQueue $deploymentQueue = null;

    #[Column(type: Types::INTEGER, nullable: true)]
    private ?int $joinConfirmationTimeoutMinutes = null;

    #[Column(type: Types::INTEGER, nullable: true)]
    private ?int $startConfirmationTimeoutMinutes = null;

    #[Column(type: Types::INTEGER, nullable: true)]
    private ?int $completionConfirmationTimeoutMinutes = null;

    public function getDeploymentQueue(): ?DeploymentQueue
    {
        return $this->deploymentQueue;
    }

    public function setDeploymentQueue(?DeploymentQueue $deploymentQueue): static
    {
        $this->deploymentQueue = $deploymentQueue;

        return $this;
    }

    public function getJoinConfirmationTimeoutMinutes(): ?int
    {
        return $this->joinConfirmationTimeoutMinutes;
    }

    public function setJoinConfirmationTimeoutMinutes(?int $joinConfirmationTimeoutMinutes): static
    {
        $this->joinConfirmationTimeoutMinutes = $joinConfirmationTimeoutMinutes;

        return $this;
    }

    public function getStartConfirmationTimeoutMinutes(): ?int
    {
        return $this->startConfirmationTimeoutMinutes;
    }

    public function setStartConfirmationTimeoutMinutes(?int $startConfirmationTimeoutMinutes): static
    {
        $this->startConfirmationTimeoutMinutes = $startConfirmationTimeoutMinutes;

        return $this;
    }

    public function getCompletionConfirmationTimeoutMinutes(): ?int
    {
        return $this->completionConfirmationTimeoutMinutes;
    }

    public function setCompletionConfirmationTimeoutMinutes(?int $completionConfirmationTimeoutMinutes): static
    {
        $this->completionConfirmationTimeoutMinutes = $completionConfirmationTimeoutMinutes;

        return $this;
    }
}
