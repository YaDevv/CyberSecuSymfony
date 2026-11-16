<?php

namespace App\Controller;

use App\Entity\PasswordHistory;
use App\Entity\User;
use App\Form\AdminUserType;
use App\Repository\LoginHistoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin')]
    public function index(UserRepository $userRepository, LoginHistoryRepository $loginHistoryRepository): Response
    {
        return $this->render('admin/index.html.twig', [
            'users' => $userRepository->findBy([], ['id' => 'ASC']),
            'logins' => $loginHistoryRepository->findAllGroupedByUser(),
        ]);
    }

    #[Route('/utilisateur/nouveau', name: 'app_admin_user_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user, ['with_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $hash = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hash);

            $entityManager->persist($user);
            $entityManager->persist(new PasswordHistory($user, $hash));
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé.');

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter un utilisateur',
        ]);
    }

    #[Route('/utilisateur/{id}/modifier', name: 'app_admin_user_edit')]
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Un administrateur ne peut pas se retirer lui-même le rôle admin (risque de blocage)
            if ($this->isCurrentUser($user) && !\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $this->addFlash('danger', 'Vous ne pouvez pas retirer votre propre rôle administrateur.');

                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur modifié.');

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier ' . $user->getEmail(),
        ]);
    }

    #[Route('/utilisateur/{id}/suspendre', name: 'app_admin_user_suspend', methods: ['POST'])]
    public function suspend(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('suspend' . $user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin');
        }

        if ($this->isCurrentUser($user)) {
            $this->addFlash('danger', 'Vous ne pouvez pas suspendre votre propre compte.');

            return $this->redirectToRoute('app_admin');
        }

        $user->setIsSuspended(!$user->isSuspended());
        $entityManager->flush();

        $this->addFlash('success', $user->isSuspended() ? 'Compte suspendu.' : 'Compte réactivé.');

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/utilisateur/{id}/supprimer', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin');
        }

        if ($this->isCurrentUser($user)) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_admin');
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur supprimé.');

        return $this->redirectToRoute('app_admin');
    }

    private function isCurrentUser(User $user): bool
    {
        $current = $this->getUser();

        return $current instanceof User && $current->getId() === $user->getId();
    }
}
