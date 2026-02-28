<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeploymentConfirmation as ConfirmationType;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class DeploymentConfirmation
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ManyToOne(targetEntity: DeploymentUser::class, inversedBy: 'confirmations')]
    private ?DeploymentUser $deploymentUser = null;

    #[Column(type: Types::ENUM, enumType: ConfirmationType::class)]
    private ?ConfirmationType $type = null;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $confirmedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeploymentUser(): ?DeploymentUser
    {
        return $this->deploymentUser;
    }

    public function setDeploymentUser(?DeploymentUser $deploymentUser): static
    {
        $this->deploymentUser = $deploymentUser;

        return $this;
    }

    public function getType(): ?ConfirmationType
    {
        return $this->type;
    }

    public function setType(?ConfirmationType $type): static
    {
        $this->type = $type;

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
