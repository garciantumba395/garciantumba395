# SMS Marketing SaaS (RDC) — socle

Plateforme SaaS de marketing SMS multi-entreprises. **Étapes faites : 1 (authentification, entreprises, rôles, isolation), 2 (contacts, groupes, import CSV) et 3 (consentement, désinscription).**
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
bash scripts/setup-local.sh   # tout-en-un (ou les étapes manuelles ci-dessous)
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

## Vérifier l'étape 2 manuellement

1. `/entreprise/parametres` : vérifier « Pays et indicatif » (RDC +243 par défaut).
2. `/groupes` : créer un groupe. `/contacts/nouveau` : saisir `0812345678` → enregistré `+243812345678`.
3. `/contacts/importer` : importer un CSV (modèle ci-dessous), puis le réimporter : 0 créé, pas de doublon.
4. Avec un 2ᵉ compte/entreprise : les contacts et groupes de la première restent invisibles, même avec une URL d'ID forgée (404).

```csv
telephone,nom,email
0812345678,Marie Exemple,marie@example.test
+243 97 123 4567,Paul Exemple,
```
N'importez **aucune donnée réelle** dans un environnement de développement. Les numéros d'exemple ci-dessus sont fictifs.

## Vérifier l'étape 3 manuellement

1. `/contacts` : un contact importé sans déclaration affiche « Sans consentement ». Ouvrir sa fiche.
2. Fiche contact : enregistrer un consentement (source, date passée, preuve) → statut « Consentement valide » ; compteur du tableau de bord mis à jour.
3. Cliquer « Désinscrire » : statut « Désinscrit », le consentement est révoqué, le formulaire disparaît.
4. `/desinscriptions` : ajouter des numéros ; seul un owner/admin peut lever (motif obligatoire) ; après levée, un **nouveau** consentement est requis.
5. Import avec case « Je certifie… » : consentements créés ; les numéros désinscrits sont ignorés.
6. Avec un 2ᵉ compte/entreprise : aucune fuite de consentements ni de désinscriptions.

Paramètres (`config/consent.php`, variables `CONSENT_REQUIRE_EVIDENCE`, `CONSENT_MAX_AGE_DAYS`) : choix techniques, **pas des règles juridiques** — à valider (D4).

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

- Aucun envoi n'existe encore (étape 6) ; la désinscription par réponse « STOP » nécessitera les SMS entrants (fournisseur, étape 10) ; l'import est synchrone.
- Pas d'invitation de membres ni de changement de rôle (étape ultérieure).
- Pas de vérification d'e-mail ni de 2FA (décision D10).
- Inscription ouverte (décision D5 tranchée ; anti-abus à reconsidérer à l'étape 9).
