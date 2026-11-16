<?php

namespace App\Tests;

/**
 * Groupe B - Modification de profil et de rôles (tests 7 et 8).
 */
final class ProfilTest extends AppWebTestCase
{
    /** Remplit le vrai formulaire de profil d'Alice (avec son jeton) et l'envoie. */
    private function soumettreProfil(array $valeurs): void
    {
        $crawler = $this->client->request('GET', '/profil/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer')->form($valeurs));
    }

    /**
     * Simule un pirate qui part du vrai formulaire (donc avec un jeton valide) et y ajoute des paramètres en plus.
     *
     * @param array<string, mixed> $ajouts Paramètres supplémentaires, au même format que la requête HTTP
     */
    private function envoyerRequeteTruquee(array $ajouts, string $uri = '/profil/modifier'): void
    {
        $crawler = $this->client->request('GET', '/profil/modifier');
        $valeurs = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $this->client->request('POST', $uri, array_replace_recursive($valeurs, $ajouts));
    }

    /** Test 7 : modification du compte d'un autre utilisateur (IDOR / BOLA). */
    public function testImpossibleDeModifierLeCompteDUnAutre(): void
    {
        $alice = $this->creerAlice();
        $bob = $this->creerBob();
        $this->seConnecterEn($alice);

        // 1. Alice modifie son propre profil : autorisé
        $this->soumettreProfil(['profile_edit[ville]' => 'Paris']);
        self::assertResponseRedirects('/profil');
        self::assertSame('Paris', $this->relire($alice)->getVille());

        // 2. Alice tente d'ouvrir la page d'édition de Bob (zone admin) : refusé
        $this->client->request('GET', sprintf('/admin/utilisateur/%d/modifier', $bob->getId()));
        self::assertResponseStatusCodeSame(403);

        // 3. Alice remplace son identifiant par celui de Bob dans l'URL : cette URL n'existe pas
        $this->client->request('GET', sprintf('/profil/%d/modifier', $bob->getId()));
        self::assertResponseStatusCodeSame(404);

        // 4. Requêtes HTTP directes visant Bob : refusées, ou sans effet sur Bob
        $this->client->request('POST', sprintf('/admin/utilisateur/%d/modifier', $bob->getId()), [
            'admin_user' => ['ville' => 'Piratée', 'nom' => 'Piratage', 'prenom' => 'Piratage', 'adresse' => '1 rue du Pirate', 'codePostal' => '00000', 'email' => 'bob@test.fr'],
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->envoyerRequeteTruquee(
            ['profile_edit' => ['ville' => 'Piratée'], 'id' => $bob->getId(), 'user' => $bob->getId()],
            '/profil/modifier?id=' . $bob->getId(),
        );

        // Bob est inchangé
        $bobRelu = $this->relire($bob);
        self::assertSame('Lyon', $bobRelu->getVille());
        self::assertSame('Test', $bobRelu->getNom());
        self::assertSame('bob@test.fr', $bobRelu->getEmail());
    }

    /** Test 8 : un utilisateur ne peut pas modifier ses propres rôles (ni ceux d'un autre). */
    public function testImpossibleDeModifierSesPropresRoles(): void
    {
        $admin = $this->creerAdmin();
        $alice = $this->creerAlice();
        $bob = $this->creerBob();
        $this->seConnecterEn($alice);

        // 1. Alice modifie son prénom : autorisé
        $this->soumettreProfil(['profile_edit[prenom]' => 'Alicia']);
        self::assertResponseRedirects('/profil');
        self::assertSame('Alicia', $this->relire($alice)->getPrenom());

        // 2. Alice tente de s'attribuer ROLE_ADMIN : le formulaire n'a pas ce champ, la requête est refusée
        $this->envoyerRequeteTruquee(['profile_edit' => ['roles' => ['ROLE_ADMIN']]]);
        self::assertResponseStatusCodeSame(422);
        self::assertNotContains('ROLE_ADMIN', $this->relire($alice)->getRoles());

        // 3. Alice ajoute un paramètre "roles" à une requête de modification valide : rôle inchangé
        $this->envoyerRequeteTruquee(['profile_edit' => ['ville' => 'Nice'], 'roles' => ['ROLE_ADMIN']]);
        self::assertResponseRedirects('/profil');
        self::assertSame('Nice', $this->relire($alice)->getVille());
        self::assertNotContains('ROLE_ADMIN', $this->relire($alice)->getRoles());

        // L'accès admin lui reste interdit
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);

        // 4. Alice tente de modifier les rôles de Bob : refusé
        $this->client->request('POST', sprintf('/admin/utilisateur/%d/modifier', $bob->getId()), [
            'admin_user' => ['nom' => 'Test', 'prenom' => 'Bob', 'adresse' => '12 rue des Lilas', 'ville' => 'Lyon', 'codePostal' => '69001', 'email' => 'bob@test.fr', 'roles' => ['ROLE_USER', 'ROLE_ADMIN']],
        ]);
        self::assertResponseStatusCodeSame(403);
        self::assertNotContains('ROLE_ADMIN', $this->relire($bob)->getRoles());

        // 5. L'administrateur attribue un rôle autorisé à Bob : autorisé
        $this->oublierLaSession();
        $this->seConnecterEn($this->relire($admin));
        $crawler = $this->client->request('GET', sprintf('/admin/utilisateur/%d/modifier', $bob->getId()));
        $formulaire = $crawler->selectButton('Enregistrer')->form();
        $formulaire['admin_user']['roles'][1]->tick(); // case "Administrateur"
        $this->client->submit($formulaire);
        self::assertResponseRedirects('/admin');
        self::assertContains('ROLE_ADMIN', $this->relire($bob)->getRoles());
    }
}
