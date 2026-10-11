<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Cet email n\'est pas valide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 30)]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\'-]+$/u',
        message: 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, max: 30)]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\'-]+$/u',
        message: 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.'
    )]
    private ?string $prenom = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    #[Assert\Length(min: 5, max: 150)]
    private ?string $adresse = null;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\d{5}$/',
        message: 'Le code postal doit contenir exactement 5 chiffres.'
    )]
    private ?string $codePostal = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSuspended = false;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(min: 2, max: 50)]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\'-]+$/u',
        message: 'La ville ne peut contenir que des lettres, espaces, apostrophes et tirets.'
    )]
    private ?string $ville = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function setIsSuspended(bool $isSuspended): static
    {
        $this->isSuspended = $isSuspended;

        return $this;
    }
}
