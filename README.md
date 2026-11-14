# SMS Marketing SaaS (RDC) — socle

Plateforme SaaS de marketing SMS multi-entreprises. **Étapes faites : 1 (authentification, entreprises, rôles, isolation), 2 (contacts, groupes, import CSV) et 3 (consentement, désinscription) 4 (fournisseur SMS fictif, journal des messages) 5 (crédits, grand livre, tarifs) et 6 (campagnes, envoi en file d'attente Redis).**
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

## Vérifier l'étape 4 manuellement

1. `/messages` : bandeau orange « Mode simulation » (aucun SMS réel n'est envoyé).
2. Owner/admin : « Envoi de test » vers un contact **avec consentement** → statut « Envoyé », badge « Simulation ».
3. Même test vers un contact **sans consentement** ou **désinscrit** → « Bloqué », rien n'est « envoyé ».
4. Dans `.env`, `SMS_PROVIDER=fake` et `SMS_SENDING_ENABLED=false` (défauts). Aucun fournisseur réel n'existe encore : mettre
   `SMS_SENDING_ENABLED=true` avec le fournisseur fictif ne change rien et n'envoie rien de réel.

## Vérifier l'étape 5 manuellement

1. Créer un administrateur de plateforme (console uniquement) :
   `php artisan tinker` puis `User::where('email','…')->first()->forceFill(['is_platform_admin'=>true])->save();`
2. Connecté en administrateur : `/admin/entreprises` → sélectionner une entreprise → ajouter `100` crédits avec un motif.
3. Connecté comme l'entreprise : `/credits` affiche 100,00 crédits (≈ 120 SMS) et l'historique ; le tableau de bord affiche le solde.
4. `/messages` : envoi de test → 1 segment = 0,83 crédit débité (marqué « simulation ») ; avec un solde insuffisant → « Bloqué (crédits insuffisants) », rien n'est envoyé.
5. `/admin/tarifs` : ajouter un tarif pays ou préfixe (saisi en « SMS pour 100 crédits ») ; il s'applique aux envois suivants.
6. Garde-fous d'envoi réel (D12) : visibles uniquement en mode réel ; en simulation rien n'est limité. `/admin/entreprises` : statut d'envoi de chaque entreprise
   (Démarrage / Validée / Suspendue), limite journalière modifiable avec motif. Seuils dans `config/sms.php` (`sending`).
7. `php artisan credits:verify` → « OK : tous les soldes correspondent au grand livre ».

Un utilisateur non administrateur reçoit une erreur 403 sur `/admin/*`.

## Vérifier l'étape 6 manuellement

1. Démarrer les services : Redis et PostgreSQL, puis **deux processus** : `php artisan queue:work redis --queue=sms` (envois) et `php artisan schedule:work` (campagnes programmées).
2. `/campagnes` → « Nouvelle campagne » : nom, message (compteur de segments en direct), groupe ou tous les contacts, date facultative → brouillon.
3. Fiche campagne : estimation (destinataires ciblables, exclus, coût, solde). Owner/admin : « Lancer » → statut « Envoi en cours » puis « Terminée » (rafraîchissement automatique).
4. Les contacts sans consentement ou désinscrits ne reçoivent rien ; une désinscription faite pendant la campagne bloque les messages restants.
5. Solde insuffisant → campagne **en pause** avec message ; ajoutez des crédits (administration) puis « Reprendre » : seuls les destinataires restants sont envoyés, sans doublon.
6. « Annuler » stoppe immédiatement. Résultats : chiffres réels du journal, marqués « simulation » tant que le fournisseur est fictif.

Sans worker, les campagnes restent en « Préparation » : c'est normal (les jobs attendent dans la file Redis `sms`).

## Vérifier l'étape 7 manuellement

1. Se connecter (propriétaire), menu **Rapports** : période par défaut = 30 derniers jours (fuseau `APP_TIMEZONE`), onglet « Envois réels » vide → « Aucune donnée sur cette période ».
2. Faire un envoi de test (Messages) puis une campagne (fournisseur fictif) : onglet **Simulation** → bandeau « Simulation : aucun SMS réel n'a été envoyé », totaux, bloqués par motif, crédits consommés, série par jour ; l'onglet « Envois réels » reste vide (jamais de total cumulé).
3. Fiche d'une campagne → « Voir le rapport détaillé de cette campagne » : mêmes chiffres que la fiche, filtrés sur cette campagne.
4. Dates invalides, fin avant début ou période > 366 jours → message d'erreur, aucun chiffre. Campagne d'une autre entreprise (URL forgée `?campaign=…`) → « Campagne introuvable ».
5. **Télécharger le CSV** (owner/admin) : ouvrir dans Excel (UTF-8 BOM, séparateur `;`). Créer une campagne nommée `=1+1` : la cellule apparaît préfixée d'une apostrophe. Un membre simple voit les agrégats mais pas le bouton, et `/rapports/export` lui répond 403.
6. Abaisser le plafond : `REPORTS_EXPORT_MAX_ROWS=1` dans `.env` → export refusé avec un message clair (rien n'est tronqué).
7. `php artisan credits:verify` reste « OK » ; « crédits consommés » = ajouts − solde actuel (hors corrections).
## Vérifier l'étape 10 (préparation) manuellement

Aucun fournisseur réel : on teste avec le fournisseur **fictif** et un secret local de démonstration (à ne jamais réutiliser ailleurs).

1. Dans `.env` : `SMS_WEBHOOK_FAKE_SECRET=secret-de-demonstration-local` (vide = tout webhook est refusé : vérifiez d'abord qu'une requête renvoie 401), puis `php artisan config:clear`.
2. Lancer le serveur (`php artisan serve`) et envoyer un accusé de réception **signé** (le message doit exister : envoyez d'abord un SMS simulé et relevez son `provider_message_id`, ex. `fake-…`, dans `/messages` ou la base) :

```bash
SECRET=secret-de-demonstration-local
BODY='{"event_id":"evt-001","type":"delivery_report","message_id":"fake-XXXXXXXXXXXXXXXX","status":"delivered"}'
TS=$(date +%s)
SIG=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -i -X POST http://localhost:8000/webhooks/sms/fake \
  -H 'Content-Type: application/json' -H "X-Fake-Timestamp: $TS" -H "X-Fake-Signature: $SIG" --data "$BODY"
```

3. Réponse attendue : `200 {"status":"applied"}` (le message passe à « Délivré »). Rejouer la même commande : `200 {"status":"duplicate"}`. Modifier un caractère du corps ou la signature : `401`.
   Un `TS` vieux de plus de 5 minutes : `401`. Un fournisseur inconnu (`/webhooks/sms/autre`) : `404`.
4. Désinscription : un « STOP » entrant (`{"event_id":"evt-002","type":"inbound","from":"+243810000001","text":"STOP"}`, signé de la même façon) ne désinscrit **que** si une entreprise a envoyé un message
   **réel** à ce numéro dans les 30 derniers jours ; avec le fournisseur fictif (messages simulés) la réponse est `{"status":"ignored"}` : c'est voulu.
5. `php artisan sms:reconcile` : liste (numéros masqués) les messages réels bloqués en « en cours »/« résultat inconnu » ; `--include-simulated` pour voir aussi les simulés. Code de sortie 1 s'il y en a (`echo $?`).
6. Tests : `php artisan test --filter=Sms` (contrats d'adaptateur, webhooks, désinscription, réconciliation).
## Vérifier l'étape 8 manuellement

1. Migrer (`php artisan migrate`), puis attribuer le statut d'administrateur **en console** (seule voie) :
   `php artisan platform:grant-admin admin@exemple.test` (compte existant). Pour le retirer : `php artisan platform:revoke-admin …` (refusé pour le dernier administrateur actif).
2. Connecté en administrateur : lien « Plateforme » dans la navigation → `/admin` : chiffres réels (entreprises, utilisateurs, messages réels et simulés **séparés**, crédits en circulation, campagnes actives, jobs en échec). Un propriétaire/membre d'entreprise reçoit **403** sur toutes les pages `/admin/*` ; un visiteur non connecté est renvoyé vers la connexion.
3. `/admin/entreprises` : recherche par nom, filtre par statut d'envoi ; cliquer une entreprise → fiche (membres, solde, statut, activité récente). Désactiver l'entreprise **sans motif** → refusé ; avec motif → ses membres perdent l'accès, ses campagnes en cours se mettent en pause. Réactiver.
4. `/admin/utilisateurs` : désactiver un compte (motif obligatoire) → il ne peut plus se connecter (« Identifiants incorrects, ou compte désactivé ») et, s'il était connecté, est déconnecté à sa prochaine action. Essayer de désactiver votre propre compte, ou le seul administrateur : refusé.
5. Faire quelques actions (ajouter des crédits, changer le statut d'envoi, lever une désinscription, mettre une campagne en pause, modifier le nom de l'entreprise) puis ouvrir `/admin/audit` : une entrée par action, avec l'acteur (ou « Système » pour les transitions automatiques), sans numéro ni texte de message. Tester les filtres (action, entreprise, acteur, période). Le journal est en lecture seule.
6. Confidentialité : aucune page `/admin/*` n'affiche de contact, numéro, texte de message ni consentement ; le statut d'administrateur ne donne accès à aucune page d'entreprise (`/contacts`, `/campagnes`… renvoient vers la création d'entreprise si l'administrateur n'en est pas membre).

## Rôles

| Rôle | Paramètres entreprise | Membres (lecture) |
|---|---|---|
| owner | oui | oui |
| admin | oui | oui |
| member | non | oui |

`users.is_platform_admin` (administrateur de la plateforme) est **séparé** des rôles et n'est pas assignable en masse ;
il ne donne aucun accès aux données d'une entreprise. Pour en définir un (console uniquement) :
`php artisan platform:grant-admin {email}` (retrait : `php artisan platform:revoke-admin {email}`, refusé pour le dernier administrateur).

## Règle d'or de l'isolation

Tout modèle métier utilise le trait `App\Models\Concerns\BelongsToCompany` et dispose de tests calqués sur
`tests/Feature/Tenancy/DataIsolationTest.php`. Sans entreprise active, les requêtes ne renvoient **rien**
et la création est refusée (fail-closed). Les jobs en file devront fixer explicitement le contexte via `CurrentCompany::set()`.

## Limites connues de l'étape 1

- Pas encore de rapports exportables (étape 7), aucun fournisseur réel (étape 10) ; pas de paiement en ligne (les crédits sont ajoutés à la main) ; la désinscription par réponse « STOP » nécessitera les SMS entrants (fournisseur, étape 10) ; l'import est synchrone.
- Pas d'invitation de membres ni de changement de rôle (étape ultérieure).
- Pas de vérification d'e-mail ni de 2FA (décision D10).
- Inscription ouverte (décision D5 tranchée ; anti-abus à reconsidérer à l'étape 9).
