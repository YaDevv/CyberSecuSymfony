<?php

namespace App\Tests;

/**
 * Groupe D - Sécurité des comptes (tests 13 à 15).
 */
final class SecuriteComptesTest extends AppWebTestCase
{
    private const MOT_DE_PASSE_B = 'Lampadaire-Cerise-Tunnel-4821!';
    private const MOT_DE_PASSE_C = 'Ardoise-Bouteille-Nuage-9054!';

    /** Test 13 : un compte suspendu ne peut plus se connecter, puis le peut à nouveau une fois réactivé. */
    public function testSuspensionEtReactivation(): void
    {
        $admin = $this->creerAdmin();
        $bob = $this->creerBob();

        // Suspension par l'administrateur
        $this->basculerSuspensionParAdmin($admin, $bob);
        self::assertTrue($this->relire($bob)->isSuspended());

        $this->soumettreConnexion('bob@test.fr', self::MOT_DE_PASSE_BOB);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Votre compte est suspendu.');
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/connexion');

        // Réactivation
        $this->basculerSuspensionParAdmin($this->relire($admin), $this->relire($bob));
        self::assertFalse($this->relire($bob)->isSuspended());

        $this->soumettreConnexion('bob@test.fr', self::MOT_DE_PASSE_BOB);
        self::assertResponseRedirects('/');
    }

    /** Test 14 : un ancien mot de passe ne peut pas être réutilisé (A puis B puis C : A et B refusés). */
    public function testInterdictionDeReutiliserUnAncienMotDePasse(): void
    {
        $alice = $this->creerAlice(); // mot de passe A
        $this->seConnecterEn($alice);

        // A -> B
        $this->soumettreChangementMotDePasse(self::MOT_DE_PASSE, self::MOT_DE_PASSE_B);
        self::assertResponseRedirects('/profil');

        // B -> C
        $this->soumettreChangementMotDePasse(self::MOT_DE_PASSE_B, self::MOT_DE_PASSE_C);
        self::assertResponseRedirects('/profil');

        // C -> A : refusé
        $this->soumettreChangementMotDePasse(self::MOT_DE_PASSE_C, self::MOT_DE_PASSE);
        self::assertResponseStatusCodeSame(422);

        // C -> B : refusé
        $this->soumettreChangementMotDePasse(self::MOT_DE_PASSE_C, self::MOT_DE_PASSE_B);
        self::assertResponseStatusCodeSame(422);

        // Le mot de passe actif est toujours C
        $this->oublierLaSession();
        $this->soumettreConnexion('alice@test.fr', self::MOT_DE_PASSE_C);
        self::assertResponseRedirects('/');
    }

    /** Test 15 : changement de mot de passe autorisé uniquement avec le bon mot de passe actuel et un nouveau conforme. */
    public function testChangementDeMotDePasseAutorise(): void
    {
        $alice = $this->creerAlice();
        $this->seConnecterEn($alice);

        // Mauvais mot de passe actuel
        $this->soumettreChangementMotDePasse('Mauvais-Actuel-1234!', self::MOT_DE_PASSE_B);
        self::assertResponseStatusCodeSame(422);

        // Nouveau mot de passe non conforme (trop court / trop simple)
        $this->soumettreChangementMotDePasse(self::MOT_DE_PASSE, 'abc');
        self::assertResponseStatusCodeSame(422);

        // Changement valide
        $this->soumettreChangementMotDePasse(self::MOT_DE_PASSE, self::MOT_DE_PASSE_B);
        self::assertResponseRedirects('/profil');

        // Connexion avec le nouveau mot de passe : OK
        $this->oublierLaSession();
        $this->soumettreConnexion('alice@test.fr', self::MOT_DE_PASSE_B);
        self::assertResponseRedirects('/');

        // Connexion avec l'ancien mot de passe : refusée
        $this->oublierLaSession();
        $this->soumettreConnexion('alice@test.fr', self::MOT_DE_PASSE);
        self::assertResponseRedirects('/connexion');
    }
}
