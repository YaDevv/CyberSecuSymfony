<?php

namespace App\Tests;

use App\Entity\User;

/**
 * Groupe B - Rôles et autorisations (tests 5 et 6).
 * Les tests 7 et 8 (IDOR / auto-attribution de rôle) sont dans ProfilTest.
 */
final class RolesTest extends AppWebTestCase
{
    /** Test 5 : /admin est réservé à l'administrateur (admin OK, utilisateur 403, visiteur redirigé). */
    public function testAccesALaZoneAdmin(): void
    {
        $admin = $this->creerAdmin();
        $alice = $this->creerAlice();

        // Visiteur : redirigé vers la connexion
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/connexion');

        // Utilisateur simple (Alice) : accès interdit
        $this->seConnecterEn($alice);
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);

        // Administrateur : accès autorisé
        $this->oublierLaSession();
        $this->seConnecterEn($admin);
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    /** Test 6 : fonctionnalités d'administration (liste, modification, suspension) et refus pour Alice. */
    public function testFonctionnalitesAdministrateur(): void
    {
        $admin = $this->creerAdmin();
        $alice = $this->creerAlice();
        $bob = $this->creerBob();

        // --- Côté administrateur ---
        $this->seConnecterEn($admin);

        // Liste des utilisateurs
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'bob@test.fr');
        self::assertSelectorTextContains('body', 'alice@test.fr');

        // Modification de Bob (la ville passe de Lyon à Paris)
        $crawler = $this->client->request('GET', sprintf('/admin/utilisateur/%d/modifier', $bob->getId()));
        self::assertResponseIsSuccessful();
        $formulaire = $crawler->selectButton('Enregistrer')->form(['admin_user[ville]' => 'Paris']);
        $this->client->submit($formulaire);
        self::assertResponseRedirects('/admin');
        self::assertSame('Paris', $this->relire($bob)->getVille());

        // Suspension de Bob
        $jeton = $this->jetonAdmin($bob, 'suspendre');
        $this->client->request('POST', sprintf('/admin/utilisateur/%d/suspendre', $bob->getId()), ['_token' => $jeton]);
        self::assertResponseRedirects('/admin');
        self::assertTrue($this->relire($bob)->isSuspended());

        // --- Côté Alice : mêmes actions refusées, contrôle fait côté serveur ---
        $this->oublierLaSession();
        $this->seConnecterEn($this->relire($alice));

        $this->client->request('POST', sprintf('/admin/utilisateur/%d/suspendre', $bob->getId()), ['_token' => 'jeton-forge']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', sprintf('/admin/utilisateur/%d/supprimer', $bob->getId()), ['_token' => 'jeton-forge']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', sprintf('/admin/utilisateur/%d/modifier', $bob->getId()));
        self::assertResponseStatusCodeSame(403);

        // Bob existe toujours et l'état de son compte n'a pas été modifié par Alice
        self::assertNotNull($this->em->getRepository(User::class)->find($bob->getId()));
        self::assertTrue($this->relire($bob)->isSuspended());
    }
}
