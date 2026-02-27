<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeploymentUser as DeploymentUserType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity]
class DeploymentUser
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ManyToOne(targetEntity: Deployment::class, inversedBy: 'users')]
    #[JoinColumn(nullable: false)]
    private ?Deployment $deployment = null;

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: false)]
    private ?User $user = null;

    #[Column(type: Types::ENUM, enumType: DeploymentUserType::class)]
    private ?DeploymentUserType $type = null;

    /** @var Collection<int, DeploymentConfirmation> $confirmations */
    #[OneToMany(targetEntity: DeploymentConfirmation::class, mappedBy: 'deploymentUser', cascade: ['persist'])]
    private Collection $confirmations;

    public function __construct()
    {
        $this->confirmations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeployment(): ?Deployment
    {
        return $this->deployment;
    }

    public function setDeployment(?Deployment $deployment): DeploymentUser
    {
        $this->deployment = $deployment;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?DeploymentUserType
    {
        return $this->type;
    }

    public function setType(?DeploymentUserType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** @return Collection<int, DeploymentConfirmation> */
    public function getConfirmations(): Collection
    {
        return $this->confirmations;
    }

    public function addConfirmation(DeploymentConfirmation $confirmation): static
    {
        if (!$this->confirmations->contains($confirmation)) {
            $this->confirmations->add($confirmation);
            $confirmation->setDeploymentUser($this);
        }

        return $this;
    }
}
