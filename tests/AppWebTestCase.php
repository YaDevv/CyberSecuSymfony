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

    /** Mot de passe de Bob (différent de celui d'Alice). */
    protected const MOT_DE_PASSE_BOB = 'Tournevis-Papillon-Glacier-5598!';

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
        $this->viderLimiteurDeConnexions();
    }

    /**
     * Le compteur anti force brute (5 échecs / 15 min) n'est pas stocké en base : on le remet à zéro
     * pour que les tests restent indépendants (sinon les échecs d'un test gêneraient les suivants).
     */
    private function viderLimiteurDeConnexions(): void
    {
        $conteneur = static::getContainer();
        if ($conteneur->has('cache.rate_limiter')) {
            $conteneur->get('cache.rate_limiter')->clear();
        }
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
        string $motDePasse = self::MOT_DE_PASSE,
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

        $hash = $hasher->hashPassword($utilisateur, $motDePasse);
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
        // Bob a son propre mot de passe, différent de celui d'Alice
        return $this->creerUtilisateur('Bob', 'bob@test.fr', motDePasse: self::MOT_DE_PASSE_BOB);
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

    /**
     * Efface les cookies du navigateur simulé : équivaut à fermer le navigateur (plus de session).
     */
    protected function oublierLaSession(): void
    {
        $this->client->getCookieJar()->clear();
    }

    /**
     * Lit le jeton CSRF d'un petit formulaire de la page /admin (suspension ou suppression).
     */
    protected function jetonAdmin(User $cible, string $action): string
    {
        $crawler = $this->client->request('GET', '/admin');
        $champ = $crawler->filter(sprintf('form[action$="/utilisateur/%d/%s"] input[name="_token"]', $cible->getId(), $action));

        return $champ->attr('value');
    }

    /**
     * Fait suspendre (ou réactiver) un compte par l'administrateur, en passant par le formulaire de /admin.
     * Le navigateur simulé est ensuite déconnecté.
     */
    protected function basculerSuspensionParAdmin(User $admin, User $cible): void
    {
        $this->seConnecterEn($admin);
        $jeton = $this->jetonAdmin($cible, 'suspendre');
        $this->client->request('POST', sprintf('/admin/utilisateur/%d/suspendre', $cible->getId()), ['_token' => $jeton]);
        $this->oublierLaSession();
    }

    /**
     * Remplit et envoie le formulaire d'inscription.
     *
     * @param array<string, string> $valeurs Valeurs à remplacer (clés : nom, prenom, adresse, ville, codePostal, email, mdp)
     */
    protected function soumettreInscription(array $valeurs = []): Crawler
    {
        $valeurs += [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'adresse' => '12 rue des Lilas',
            'ville' => 'Lyon',
            'codePostal' => '69001',
            'email' => 'nouveau@test.fr',
            'mdp' => self::MOT_DE_PASSE,
        ];

        $crawler = $this->client->request('GET', '/inscription');
        $formulaire = $crawler->selectButton('Créer mon compte')->form([
            'registration[nom]' => $valeurs['nom'],
            'registration[prenom]' => $valeurs['prenom'],
            'registration[adresse]' => $valeurs['adresse'],
            'registration[ville]' => $valeurs['ville'],
            'registration[codePostal]' => $valeurs['codePostal'],
            'registration[email]' => $valeurs['email'],
            'registration[plainPassword][first]' => $valeurs['mdp'],
            'registration[plainPassword][second]' => $valeurs['mdp'],
        ]);

        return $this->client->submit($formulaire);
    }

    /**
     * Remplit et envoie le formulaire de changement de mot de passe (utilisateur déjà connecté).
     */
    protected function soumettreChangementMotDePasse(string $actuel, string $nouveau): Crawler
    {
        $crawler = $this->client->request('GET', '/password');
        $formulaire = $crawler->selectButton('Modifier mon mot de passe')->form();

        $valeurs = [];
        foreach (array_keys($formulaire->getValues()) as $nom) {
            if (str_ends_with($nom, '[currentPassword]')) {
                $valeurs[$nom] = $actuel;
            } elseif (str_ends_with($nom, '[plainPassword][first]') || str_ends_with($nom, '[plainPassword][second]')) {
                $valeurs[$nom] = $nouveau;
            }
        }

        return $this->client->submit($formulaire, $valeurs);
    }
}
