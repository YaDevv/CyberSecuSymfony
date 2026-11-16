# Gestion des accès et des habilitations

Application Symfony 7.4 sécurisée (projet de cybersécurité, épreuve E5 du BTS SIO).
Elle sert de **base réutilisable** pour de nouveaux projets : inscription, connexion,
gestion des mots de passe, administration des comptes et historique des connexions.

## Fonctionnalités

- **Inscription** (nom, prénom, adresse, ville, code postal, email, mot de passe) avec validation
  par les contraintes Symfony (`NotBlank`, `Length`, `Regex`, `Email`, `UniqueEntity`...).
- **Mot de passe sécurisé** : 12 caractères minimum, majuscule, minuscule, chiffre, caractère spécial,
  score de robustesse (`PasswordStrength`) et vérification de fuite (`NotCompromisedPassword`).
  Les règles sont centralisées dans `src/Validator/PasswordRules.php`.
- **Connexion / déconnexion** avec jeton CSRF.
- **Protection contre la force brute** : `login_throttling` (5 échecs en 15 minutes).
- **Changement de mot de passe** (`/password`) : impossible de réutiliser le mot de passe actuel
  ni l'un des 5 précédents (table `password_history`, mots de passe stockés hachés).
- **Administration** (`/admin`, rôle `ROLE_ADMIN`) : ajouter, modifier, supprimer, suspendre un
  utilisateur et gérer ses rôles. Un compte suspendu ne peut plus se connecter.
- **Historique des connexions** : date et heure de chaque connexion réussie, affichées dans la liste
  des utilisateurs.

## Prérequis

- PHP 8.2 ou plus, avec les extensions habituelles de Symfony
- Composer
- Une base de données **MySQL ou MariaDB** (les migrations fournies sont générées pour MySQL/MariaDB)

## Installation

```bash
git clone https://github.com/YaDevv/CyberSecuSymfony.git
cd CyberSecuSymfony
composer install
```

Créez un fichier `.env.local` (ignoré par Git) à la racine du projet :

```dotenv
APP_SECRET=une-valeur-aleatoire-de-32-caracteres
DATABASE_URL="mysql://utilisateur:motdepasse@127.0.0.1:3306/nom_base?serverVersion=8.0&charset=utf8mb4"
```

Pour générer un `APP_SECRET` :

```bash
php -r "echo bin2hex(random_bytes(16));"
```

> Pour utiliser un autre SGBD (PostgreSQL par exemple), changez `DATABASE_URL`, supprimez le dossier
> `migrations/` et régénérez les migrations avec `php bin/console make:migration`.

Puis créez la base et les tables :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

> Ne versionnez jamais `.env.local` : il contient vos secrets.

## Créer le premier administrateur

L'inscription crée uniquement des utilisateurs simples. Inscrivez-vous sur `/inscription`, puis
promouvez votre compte directement en base :

```sql
UPDATE user SET roles = '["ROLE_ADMIN"]' WHERE email = 'votre-email@exemple.fr';
```

Déconnectez-vous puis reconnectez-vous.

## Routes principales

| Route | Description | Accès |
|---|---|---|
| `/inscription` | Création de compte | Public |
| `/connexion`, `/deconnexion` | Authentification | Public |
| `/password` | Changement de mot de passe | Utilisateur connecté |
| `/admin` | Gestion des utilisateurs et historique | Administrateur |

## Structure utile

| Fichier | Rôle |
|---|---|
| `config/packages/security.yaml` | Pare-feu, `form_login`, `login_throttling`, `access_control` |
| `src/Entity/User.php` | Utilisateur et contraintes de validation |
| `src/Entity/PasswordHistory.php` | Historique des mots de passe hachés |
| `src/Entity/LoginHistory.php` | Historique des connexions |
| `src/Validator/PasswordRules.php` | Règles du mot de passe (inscription, changement, création admin) |
| `src/Security/UserChecker.php` | Refuse la connexion d'un compte suspendu |
| `src/EventListener/LoginSuccessListener.php` | Enregistre chaque connexion réussie |

## Réutiliser ce projet comme base

1. Clonez le dépôt (ou utilisez-le comme modèle GitHub) puis suivez l'installation ci-dessus.
2. Adaptez l'entité `User` (champs métier) et les formulaires `RegistrationType` et `AdminUserType`.
3. Ajustez les règles du mot de passe dans `PasswordRules.php` et la taille de l'historique
   (`HISTORY_SIZE` dans `PasswordController`).
4. Ajustez le seuil anti force brute dans `login_throttling` (`security.yaml`).
5. Ajoutez vos propres routes et protégez-les avec `access_control` ou `#[IsGranted(...)]`.

## Déploiement automatique (serveur de test)

À chaque modification de la branche `main`, une GitHub Action met à jour le serveur de test :
récupération du code, `composer install`, migrations, `cache:clear`, puis vérification que le site
répond. Elle est décrite dans `.github/workflows/deploy.yml` et s'exécute sur un runner auto-hébergé
installé sur le serveur de test.

- Travaillez sur une branche, ouvrez une pull request, puis fusionnez dans `main` : le déploiement
  se lance seul.
- Le suivi se fait dans l'onglet **Actions** du dépôt (pastille verte = succès).
- Aucun secret n'est stocké dans le dépôt : l'Action utilise le jeton `GITHUB_TOKEN` fourni par
  GitHub, en lecture seule.
- Le fichier `.env.local` du serveur n'est jamais modifié par le déploiement.

## Sécurité du serveur web

Le serveur web ne doit publier que le dossier `public/`. Sinon `.env.local`, `.git` ou `config/`
seraient téléchargeables. Exemple de configuration Apache (`/etc/apache2/conf-available/`) :

```apache
Alias /NomDuProjet /var/www/html/NomDuProjet/public
<Directory /var/www/html/NomDuProjet/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

Vérifiez ensuite que ces adresses répondent `404` : `/.env.local`, `/.git/HEAD`, `/composer.json`.

| `/` | Page d'accueil (boutons selon le rôle) | Public |
| `/profil` | Informations du compte et accès au changement de mot de passe | Utilisateur connecté |

## Tests automatisés

Les tests utilisent PHPUnit et les outils de test de Symfony. Ils tournent en environnement `test`, avec une base de données dédiée (fichier SQLite `var/test.db`, défini dans `.env.test`) qui est recréée avant chaque test : ils ne touchent jamais aux données du développement ni du serveur de test.

```bash
composer install                 # installe aussi PHPUnit (dépendances de développement)
vendor/bin/phpunit                  # lance tous les tests
vendor/bin/phpunit --testdox        # affiche le résultat sous forme lisible
vendor/bin/phpunit --filter Environnement   # lance un seul groupe de tests
```
