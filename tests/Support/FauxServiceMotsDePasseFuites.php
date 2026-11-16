<?php

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * « Mock » du service Have I Been Pwned utilisé par la contrainte NotCompromisedPassword.
 *
 * En environnement de test, le client HTTP de Symfony est remplacé par ce faux service :
 * les tests ne dépendent donc ni d'Internet ni du vrai service, et le résultat est reproductible.
 * Un seul mot de passe est déclaré « compromis » : MOT_DE_PASSE_COMPROMIS.
 */
final class FauxServiceMotsDePasseFuites
{
    public const MOT_DE_PASSE_COMPROMIS = 'Azerty123456!';

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        $empreinte = strtoupper(sha1(self::MOT_DE_PASSE_COMPROMIS));
        $prefixe = substr($empreinte, 0, 5);
        $suffixe = substr($empreinte, 5);

        // Le vrai service renvoie, pour un préfixe de 5 caractères, la liste des suffixes connus.
        $corps = str_ends_with($url, $prefixe)
            ? $suffixe . ":4242\r\n"
            : "0000000000000000000000000000000000A:1\r\n";

        return new MockResponse($corps);
    }
}
