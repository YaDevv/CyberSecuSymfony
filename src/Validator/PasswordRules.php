<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Règles de sécurité du mot de passe, partagées par l'inscription
 * et le changement de mot de passe (une seule source de vérité).
 */
final class PasswordRules
{
    /**
     * @return list<Constraint>
     */
    public static function constraints(): array
    {
        return [
            new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
            new Assert\Length(
                min: 12,
                minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                max: 4096,
            ),
            new Assert\PasswordStrength(
                minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
                message: 'Ce mot de passe est trop faible.',
            ),
            new Assert\Regex(
                pattern: '/[A-Z]/',
                message: 'Le mot de passe doit contenir au moins une majuscule.'
            ),
            new Assert\Regex(
                pattern: '/[a-z]/',
                message: 'Le mot de passe doit contenir au moins une minuscule.'
            ),
            new Assert\Regex(
                pattern: '/\d/',
                message: 'Le mot de passe doit contenir au moins un chiffre.'
            ),
            new Assert\Regex(
                pattern: '/[^A-Za-z0-9]/',
                message: 'Le mot de passe doit contenir au moins un caractère spécial.'
            ),
            new Assert\NotCompromisedPassword(
                message: 'Ce mot de passe a fuité dans une base de données piratée, choisis-en un autre.'
            ),
        ];
    }
}
