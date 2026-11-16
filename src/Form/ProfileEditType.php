<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de modification de SON PROPRE profil.
 * Il ne contient volontairement ni rôles, ni email, ni suspension : un utilisateur ne peut pas
 * modifier ses habilitations (un champ envoyé en plus est rejeté par le formulaire).
 */
class ProfileEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('prenom', null, ['label' => 'Prénom'])
            ->add('adresse')
            ->add('ville')
            ->add('codePostal', null, ['label' => 'Code postal'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
