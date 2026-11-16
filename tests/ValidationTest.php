<?php

namespace App\Tests;

use App\Entity\User;
use App\Tests\Support\FauxServiceMotsDePasseFuites;

/**
 * Groupe C - Validation des données d'inscription (tests 9 à 12).
 */
final class ValidationTest extends AppWebTestCase
{
    private function nombreUtilisateurs(): int
    {
        $this->em->clear();

        return $this->em->getRepository(User::class)->count([]);
    }

    /** Test 9 : tous les champs sont obligatoires. */
    public function testChampsObligatoires(): void
    {
        $this->soumettreInscription([
            'nom' => '',
            'prenom' => '',
            'adresse' => '',
            'ville' => '',
            'codePostal' => '',
            'email' => '',
            'mdp' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        foreach (['Le nom est obligatoire.', 'Le prénom est obligatoire.', 'L\'adresse est obligatoire.', 'La ville est obligatoire.', 'Le code postal est obligatoire.', 'L\'email est obligatoire.'] as $message) {
            self::assertSelectorTextContains('body', $message);
        }
        self::assertSame(0, $this->nombreUtilisateurs());
    }

    /**
     * Test 10 : les données invalides sont refusées (code postal, email, nom trop long, mot de passe court ou compromis).
     *
     * @return iterable<string, array{0: array<string, string>, 1: string}>
     */
    public static function donneesInvalides(): iterable
    {
        yield 'code postal ABCDE' => [['codePostal' => 'ABCDE'], 'code postal'];
        yield 'email invalide' => [['email' => 'pas-un-email'], 'pas valide'];
        yield 'nom trop long' => [['nom' => str_repeat('A', 31)], 'trop long|too long'];
        yield 'mot de passe trop court' => [['mdp' => 'Ab1!'], '12 caractères'];
        yield 'mot de passe compromis' => [['mdp' => FauxServiceMotsDePasseFuites::MOT_DE_PASSE_COMPROMIS], 'fuité'];
    }

    /**
     * @param array<string, string> $valeurs
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('donneesInvalides')]
    public function testDonneesInvalidesRefusees(array $valeurs, string $messageAttendu): void
    {
        $this->soumettreInscription($valeurs);

        self::assertResponseStatusCodeSame(422);
        self::assertMatchesRegularExpression('/' . $messageAttendu . '/i', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->nombreUtilisateurs());
    }

    /** Test 11 : une inscription valide crée le compte, avec un mot de passe haché. */
    public function testInscriptionValide(): void
    {
        $this->soumettreInscription();

        self::assertResponseRedirects('/connexion');

        $this->em->clear();
        $utilisateur = $this->em->getRepository(User::class)->findOneBy(['email' => 'nouveau@test.fr']);
        self::assertNotNull($utilisateur);
        self::assertNotSame(self::MOT_DE_PASSE, $utilisateur->getPassword(), 'Le mot de passe ne doit jamais être stocké en clair.');
        self::assertFalse($utilisateur->isSuspended());
        self::assertNotContains('ROLE_ADMIN', $utilisateur->getRoles(), 'Un nouvel inscrit ne doit pas être administrateur.');

        // Le nouveau compte peut se connecter
        $this->soumettreConnexion('nouveau@test.fr', self::MOT_DE_PASSE);
        self::assertResponseRedirects('/');
    }

    /** Test 12 : après plusieurs refus, aucun compte n'a été créé en base. */
    public function testAucunCompteCreeApresLesRefus(): void
    {
        $this->soumettreInscription(['codePostal' => 'ABCDE']);
        $this->soumettreInscription(['email' => 'pas-un-email']);
        $this->soumettreInscription(['mdp' => 'Ab1!']);
        $this->soumettreInscription(['mdp' => FauxServiceMotsDePasseFuites::MOT_DE_PASSE_COMPROMIS]);

        self::assertSame(0, $this->nombreUtilisateurs());
    }
}
