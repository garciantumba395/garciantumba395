# SMS Marketing SaaS (RDC) — socle

Plateforme SaaS de marketing SMS multi-entreprises. **Étape 1 : authentification, entreprises, rôles, isolation des données.**
Aucun SMS n'est envoyé par ce socle (aucun fournisseur SMS n'est encore intégré).

- Architecture : [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Plan par étapes : [docs/ROADMAP.md](docs/ROADMAP.md)
- Décisions en attente : [docs/DECISIONS.md](docs/DECISIONS.md)
- Sécurité / règles du dépôt : [docs/SECURITY.md](docs/SECURITY.md)

## Prérequis

PHP ≥ 8.3 (extensions : `pdo_pgsql`, `redis`, `mbstring`, `intl`, `xml`, `curl`, `zip`), Composer 2, Node ≥ 22,
PostgreSQL ≥ 16, Redis ≥ 7.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate

# PostgreSQL (exemple, mot de passe à choisir vous-même)
sudo -u postgres psql -c "CREATE USER sms WITH PASSWORD '<mot-de-passe>' CREATEDB;"
sudo -u postgres psql -c "CREATE DATABASE sms_saas OWNER sms;"
sudo -u postgres psql -c "CREATE DATABASE sms_saas_test OWNER sms;"
# puis renseigner DB_PASSWORD dans .env

php artisan migrate
npm install && npm run build      # ou `npm run dev` pendant le développement
php artisan serve                 # http://localhost:8000
```

`.env` n'est jamais versionné. Ne commitez ni clé ni donnée client.

## Tests

```bash
php artisan test                  # SQLite en mémoire (rapide, par défaut)

# Contre PostgreSQL (comme en CI) :
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=sms_saas_test \
DB_USERNAME=sms DB_PASSWORD=<mot-de-passe> php artisan test

vendor/bin/pint --test            # style de code
```

## Vérifier l'étape 1 manuellement

1. Ouvrir `/inscription`, créer un compte + une entreprise → arrivée sur le tableau de bord (rôle « Propriétaire »).
2. Se déconnecter, se reconnecter (`/connexion`). 5 échecs consécutifs bloquent temporairement.
3. `/entreprises/nouvelle` : créer une 2ᵉ entreprise ; le sélecteur d'entreprise apparaît dans l'en-tête.
4. Créer un 2ᵉ compte avec une autre entreprise : il ne voit jamais les membres de la première (`/entreprise/membres`).
5. Mot de passe oublié : avec `MAIL_MAILER=log`, le lien est écrit dans `storage/logs/laravel.log`.

## Rôles

| Rôle | Paramètres entreprise | Membres (lecture) |
|---|---|---|
| owner | oui | oui |
| admin | oui | oui |
| member | non | oui |

`users.is_platform_admin` (administrateur de la plateforme) est **séparé** des rôles et n'est pas assignable en masse ;
il ne donne aucun accès aux données d'une entreprise. Pour en définir un (console uniquement) :
`php artisan tinker` puis `User::where('email','…')->first()->forceFill(['is_platform_admin'=>true])->save();`

## Règle d'or de l'isolation

Tout modèle métier utilise le trait `App\Models\Concerns\BelongsToCompany` et dispose de tests calqués sur
`tests/Feature/Tenancy/DataIsolationTest.php`. Sans entreprise active, les requêtes ne renvoient **rien**
et la création est refusée (fail-closed). Les jobs en file devront fixer explicitement le contexte via `CurrentCompany::set()`.

## Limites connues de l'étape 1

- Pas d'invitation de membres ni de changement de rôle (étape ultérieure).
- Pas de vérification d'e-mail ni de 2FA (décision D10).
- Inscription ouverte (décision D5).
