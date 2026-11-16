<?php

namespace App\Tests;

use App\Entity\User;

/**
 * Vérifie que l'environnement de test lui-même est correctement configuré.
 */
final class EnvironnementTest extends AppWebTestCase
{
    public function testLeNoyauTourneEnEnvironnementDeTest(): void
    {
        self::assertSame('test', static::$kernel->getEnvironment());
    }

    public function testLaBaseDeTestEstDistincteDeCelleDuDeveloppement(): void
    {
        $params = $this->em->getConnection()->getParams();
        $nomBase = strtolower((string) ($params['dbname'] ?? $params['path'] ?? ''));

        self::assertStringContainsString('test', $nomBase);
    }

    public function testLaPageDeConnexionRepond(): void
    {
        $this->client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
    }

    /**
     * Les deux tests suivants prouvent l'isolation : quel que soit l'ordre d'exécution,
     * chacun trouve une base vide, puis crée un utilisateur sans gêner l'autre.
     */
    public function testIsolationPremierTest(): void
    {
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));

        $this->creerAlice();

        self::assertSame(1, $this->em->getRepository(User::class)->count([]));
    }

    public function testIsolationSecondTest(): void
    {
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));

        $this->creerAlice();

        self::assertSame(1, $this->em->getRepository(User::class)->count([]));
    }

    public function testLesDonneesDeTestSontPreparees(): void
    {
        $this->creerAlice();
        $this->creerBob();
        $this->creerAdmin();
        $suspendu = $this->creerUtilisateur('Charlie', 'charlie@test.fr', [], true);

        self::assertSame(4, $this->em->getRepository(User::class)->count([]));
        self::assertTrue($suspendu->isSuspended());
        self::assertContains('ROLE_ADMIN', $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.fr'])->getRoles());
    }

    public function testUnVraiUtilisateurPeutSeConnecterAvecLesDonneesDeTest(): void
    {
        $this->creerAlice();

        $this->soumettreConnexion('alice@test.fr', self::MOT_DE_PASSE);

        self::assertResponseRedirects('/');
    }
}
