<?php

namespace App\EventListener;

use App\Entity\LoginHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Enregistre la date et l'heure de chaque connexion réussie.
 */
final class LoginSuccessListener
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->entityManager->persist(new LoginHistory($user));
        $this->entityManager->flush();
    }
}
