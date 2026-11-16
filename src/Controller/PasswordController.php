<?php

namespace App\Controller;

use App\Entity\PasswordHistory;
use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Repository\PasswordHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PasswordController extends AbstractController
{
    /** Nombre d'anciens mots de passe qui ne peuvent pas être réutilisés. */
    private const HISTORY_SIZE = 5;

    #[Route('/password', name: 'app_password')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        PasswordHistoryRepository $historyRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $newPassword */
            $newPassword = $form->get('plainPassword')->getData();

            // Mot de passe actuel + HISTORY_SIZE précédents
            $previousHashes = [];
            $previousHashes[] = (string) $user->getPassword();

            foreach ($previousHashes as $oldHash) {
                if (password_verify($newPassword, $oldHash)) {
                    $form->get('plainPassword')->get('first')->addError(new FormError(
                        sprintf('Vous ne pouvez pas réutiliser l\'un de vos %d derniers mots de passe.', self::HISTORY_SIZE)
                    ));

                    return $this->render('password/index.html.twig', ['passwordForm' => $form]);
                }
            }

            $newHash = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($newHash);

            $entityManager->persist(new PasswordHistory($user, $newHash));
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a bien été modifié.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('password/index.html.twig', ['passwordForm' => $form]);
    }
}
