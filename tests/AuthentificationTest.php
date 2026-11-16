<?php

namespace App\Tests;

use Symfony\Component\BrowserKit\Cookie;

/**
 * Groupe A - Authentification (tests 1 à 4).
 */
final class AuthentificationTest extends AppWebTestCase
{
    /** Test 1 : un visiteur non connecté ne peut pas ouvrir une page protégée. */
    public function testUnVisiteurNePeutPasAccederAUnePageProtegee(): void
    {
        foreach (['/profil', '/password'] as $url) {
            $this->client->request('GET', $url);

            self::assertResponseRedirects('/connexion', null, "Le visiteur doit être renvoyé vers la connexion depuis $url");
        }
    }

    /** Test 2 : une connexion avec des identifiants valides réussit. */
    public function testConnexionAvecIdentifiantsValides(): void
    {
        $this->creerAlice();

        $this->soumettreConnexion('alice@test.fr', self::MOT_DE_PASSE);
        self::assertResponseRedirects('/');

        // La session est bien ouverte : la page protégée est maintenant accessible
        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
    }

    /** Test 3 : un mauvais mot de passe est refusé avec un message générique. */
    public function testConnexionAvecMauvaisMotDePasse(): void
    {
        $this->creerAlice();

        $this->soumettreConnexion('alice@test.fr', 'Mauvais-Mot-De-Passe-1!');
        self::assertResponseRedirects('/connexion');

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Email ou mot de passe incorrect.');

        // Aucune session n'a été ouverte
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/connexion');
    }

    /** Test 4 : après la déconnexion, la session est invalidée, même avec l'ancien cookie. */
    public function testDeconnexionInvalideLaSession(): void
    {
        $this->creerAlice();
        $this->soumettreConnexion('alice@test.fr', self::MOT_DE_PASSE);

        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();

        // On garde une copie des cookies de la session ouverte (le "vieux cookie" que volerait un pirate)
        $anciensCookies = [];
        foreach ($this->client->getCookieJar()->all() as $cookie) {
            $anciensCookies[] = new Cookie($cookie->getName(), $cookie->getValue(), null, $cookie->getPath(), $cookie->getDomain());
        }
        self::assertNotEmpty($anciensCookies);

        $this->client->request('GET', '/deconnexion');
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/connexion', null, 'Après la déconnexion, /profil doit être protégé.');

        // On rejoue l'ancien cookie : le serveur ne doit plus le reconnaître
        $this->oublierLaSession();
        foreach ($anciensCookies as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/connexion', null, 'L\'ancien cookie de session ne doit plus fonctionner.');
    }
}
