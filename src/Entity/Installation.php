<?php

namespace App\Entity;

use App\Repository\InstallationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstallationRepository::class)]
class Installation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $domain = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $installedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    public function getInstalledAt(): ?\DateTimeInterface
    {
        return $this->installedAt;
    }

    public function setInstalledAt(\DateTimeInterface $installedAt): static
    {
        $this->installedAt = $installedAt;
        return $this;
    }
}