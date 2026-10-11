<?php

namespace App\Entity;

use App\Repository\LoginHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne par connexion réussie (date et heure).
 */
#[ORM\Entity(repositoryClass: LoginHistoryRepository::class)]
#[ORM\Index(name: 'idx_login_history_user', columns: ['user_id'])]
class LoginHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $connectedAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->connectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getConnectedAt(): \DateTimeImmutable
    {
        return $this->connectedAt;
    }
}
