<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        // Les dates sont enregistrées en UTC, quel que soit le fuseau du serveur.
        // L'affichage est converti dans config/packages/twig.yaml (date.timezone).
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
    }
}
