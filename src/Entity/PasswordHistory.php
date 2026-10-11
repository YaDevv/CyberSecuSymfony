<?php

namespace App\Entity;

use App\Repository\PasswordHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique des mots de passe (hachés) d'un utilisateur,
 * utilisé pour interdire la réutilisation d'un ancien mot de passe.
 */
#[ORM\Entity(repositoryClass: PasswordHistoryRepository::class)]
#[ORM\Index(name: 'idx_password_history_user', columns: ['user_id'])]
class PasswordHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $hash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $hash)
    {
        $this->user = $user;
        $this->hash = $hash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
