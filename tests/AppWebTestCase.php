<?php

namespace App\Tests;

use App\Entity\PasswordHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Classe de base de tous les tests fonctionnels.
 *
 * - Isolation : la base de données de test est entièrement recréée avant chaque test,
 *   donc les tests sont indépendants les uns des autres et rejouables à volonté.
 * - Sécurité : un garde-fou refuse de s'exécuter si la base n'est pas une base de test,
 *   pour ne jamais effacer les données du développement ou du serveur de test.
 * - Fixtures : des méthodes préparent des utilisateurs reproductibles (Alice, Bob, admin...).
 */
abstract class AppWebTestCase extends WebTestCase
{
    /** Mot de passe commun des utilisateurs de test (conforme à la politique de sécurité). */
    protected const MOT_DE_PASSE = 'Cheval-Batterie-Agrafe-7731!';

    protected KernelBrowser $client;

    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        // L'en-tête Origin simule un vrai navigateur : le jeton CSRF « sans état » du projet
        // est vérifié à partir de l'origine de la requête.
        $this->client = static::createClient([], ['HTTP_ORIGIN' => 'http://localhost']);
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->verifierBaseDeTest();
        $this->reinitialiserBase();
    }

    /**
     * Refuse de continuer si la base utilisée n'est pas une base de test.
     */
    private function verifierBaseDeTest(): void
    {
        $environnement = static::$kernel->getEnvironment();
        $params = $this->em->getConnection()->getParams();
        $nomBase = strtolower((string) ($params['dbname'] ?? $params['path'] ?? ''));

        if ('test' !== $environnement || !str_contains($nomBase, 'test')) {
            throw new \LogicException(sprintf(
                'Garde-fou : les tests doivent utiliser l\'environnement "test" et une base dont le nom contient "test" (environnement : "%s", base : "%s").',
                $environnement,
                $nomBase
            ));
        }
    }

    /**
     * Supprime puis recrée toutes les tables : chaque test part d'une base vide.
     */
    private function reinitialiserBase(): void
    {
        $outil = new SchemaTool($this->em);
        $metadonnees = $this->em->getMetadataFactory()->getAllMetadata();
        $outil->dropSchema($metadonnees);
        $outil->createSchema($metadonnees);
    }

    /**
     * Fixture : crée un utilisateur avec le mot de passe MOT_DE_PASSE.
     *
     * @param list<string> $roles
     */
    protected function creerUtilisateur(
        string $prenom,
        string $email,
        array $roles = [],
        bool $suspendu = false,
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = (new User())
            ->setPrenom($prenom)
            ->setNom('Test')
            ->setEmail($email)
            ->setAdresse('12 rue des Lilas')
            ->setCodePostal('69001')
            ->setVille('Lyon')
            ->setRoles($roles)
            ->setIsSuspended($suspendu);

        $hash = $hasher->hashPassword($utilisateur, self::MOT_DE_PASSE);
        $utilisateur->setPassword($hash);

        $this->em->persist($utilisateur);
        $this->em->persist(new PasswordHistory($utilisateur, $hash));
        $this->em->flush();

        return $utilisateur;
    }

    protected function creerAlice(): User
    {
        return $this->creerUtilisateur('Alice', 'alice@test.fr');
    }

    protected function creerBob(): User
    {
        return $this->creerUtilisateur('Bob', 'bob@test.fr');
    }

    protected function creerAdmin(): User
    {
        return $this->creerUtilisateur('Admin', 'admin@test.fr', ['ROLE_ADMIN']);
    }

    /**
     * Simule un utilisateur déjà authentifié (sans passer par le formulaire).
     */
    protected function seConnecterEn(User $utilisateur): void
    {
        $this->client->loginUser($utilisateur, 'main');
    }

    /**
     * Passe réellement par le formulaire de connexion.
     */
    protected function soumettreConnexion(string $email, string $motDePasse): Crawler
    {
        $this->client->request('GET', '/connexion');
        $formulaire = $this->client->getCrawler()
            ->selectButton('Se connecter')
            ->form(['_username' => $email, '_password' => $motDePasse]);

        $this->client->submit($formulaire);

        return $this->client->getCrawler();
    }

    /**
     * Recharge un utilisateur depuis la base (après une requête qui l'a modifié).
     */
    protected function relire(User $utilisateur): User
    {
        $this->em->clear();

        return $this->em->getRepository(User::class)->find($utilisateur->getId());
    }
}
