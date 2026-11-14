# Architecture

Plateforme SaaS de marketing SMS multi-entreprises (cible initiale : RDC).

## Stack (versions vérifiées le 2026-11-14)

| Composant | Version | Remarque |
|---|---|---|
| PHP | 8.3+ | requis par Laravel 13 |
| Laravel | 13.x | dernière majeure stable |
| Livewire | 4.x | composants « full-page » |
| PostgreSQL | 16 | base principale (SQLite en mémoire pour les tests rapides) |
| Redis | 7 | files d'attente, verrous (étapes ultérieures) |
| Node / Vite / Tailwind | Node 22 | build des assets |

Les versions sont à **re-vérifier avant chaque montée de version** (`composer outdated`).

## Principes

1. **Monolithe modulaire** : un seul dépôt, un seul déploiement. Le code métier vit dans `app/Domain/*`
   (à partir de l'étape 2) ; `app/Livewire` ne contient que l'UI.
2. **Multi-tenant à base partagée** : toutes les tables métier portent `company_id`.
   L'isolation est garantie par :
   - le trait `BelongsToCompany` (global scope + remplissage automatique de `company_id`),
   - le service `CurrentCompany` (entreprise active, issue de la session et **revérifiée** contre les adhésions),
   - les policies/rôles,
   - des tests d'isolation obligatoires pour chaque nouveau modèle (voir `tests/Feature/Tenancy`).
   Défense en profondeur envisagée à l'étape 3 : Row-Level Security PostgreSQL (décision D6).
3. **Rôles par adhésion** : un utilisateur peut appartenir à plusieurs entreprises, avec un rôle par entreprise
   (`owner`, `admin`, `member`). L'administrateur de la plateforme est un drapeau distinct
   (`users.is_platform_admin`) qui ne donne **aucun** accès implicite aux données d'une entreprise.
4. **Fournisseur SMS interchangeable** : interface `SmsProvider`, implémentation `FakeSmsProvider` par défaut.
   Aucun envoi réel sans activation explicite (`SMS_SENDING_ENABLED=true` + fournisseur réel configuré).
5. **Idempotence** : chaque envoi et chaque débit de crédits porte une clé d'idempotence unique
   (contrainte `UNIQUE` en base) ; le grand livre de crédits est en ajout seul.
6. **Consentement avant envoi** : vérification consentement + désinscription au moment de l'envoi
   (pas seulement à la création de la campagne).
7. **Aucun secret / donnée client dans le dépôt** : `.env` ignoré, seeders sans données réelles.

## Modèle de données (étape 1)

```
users(id, name, email*, password, is_platform_admin, ...)
companies(id, name, slug*, is_active, ...)
company_user(company_id, user_id, role, PK(company_id,user_id))   -- adhésions + rôle
```

## Modèle de données (étape 2)

```
companies.country (ISO alpha-2, défaut CD)
contacts(id, company_id, name?, phone E.164, email?)        UNIQUE(company_id, phone), UNIQUE(id, company_id)
contact_groups(id, company_id, name)                        UNIQUE(company_id, name), UNIQUE(id, company_id)
contact_group_contact(company_id, contact_id, contact_group_id)
    FK composites (contact_id, company_id) et (contact_group_id, company_id) : la base refuse tout lien inter-entreprises
contact_imports(id, company_id, user_id?, group?, filename, compteurs, errors[{line, reason}])
```

- `PhoneNormalizer` (libphonenumber) : normalise en E.164 selon le pays de l'entreprise, n'accepte que les mobiles.
- `ContactImporter` : CSV (UTF-8/Windows-1252, `,` `;` tab), 5 Mo / 10 000 lignes max, idempotent (clé = numéro),
  fichier non conservé, rapport sans données personnelles. Synchrone pour l'instant (passage en file à l'étape 6 si les volumes l'exigent, D11).
- Un import seul ne rend **jamais** un contact ciblable (voir étape 3).

## Modèle de données (étape 3) — consentement et désinscription

```
consents(id, company_id, contact_id, source, evidence, obtained_at, recorded_by, revoked_at/by, revoke_reason)
    FK composite (contact_id, company_id) ; ajout seul : une révocation renseigne revoked_at
suppressions(id, company_id, phone E.164, source, reason, created_by, lifted_at/by, lift_reason)
    UNIQUE partiel (company_id, phone) WHERE lifted_at IS NULL ; indexées par NUMÉRO
```

Règles :
- **Ciblable = consentement valide (non révoqué, non expiré si configuré) ET numéro non désinscrit.** Définition unique :
  `Contact::sendable()` (envois en masse) et `SendEligibility::for($contact)` (avant chaque envoi unitaire, lit toujours la base, sans cache).
- La désinscription **prime** sur tout consentement, révoque les consentements existants et survit à la suppression/recréation du contact.
- Lever une désinscription : owner/admin, motif obligatoire, historique conservé ; **les anciens consentements ne revivent pas** (nouveau consentement requis).
- Enregistrer un consentement pour un numéro désinscrit est refusé tant que la désinscription n'est pas levée.
- Import : consentement créé seulement si une déclaration (source, preuve, date) est fournie ; numéros désinscrits ignorés.
- Suppression d'un contact : ses consentements sont supprimés avec lui (effacement) ; la désinscription (numéro seul) est conservée.
- **L'étape 6 (envoi) DOIT appeler `SendEligibility::for()` juste avant chaque envoi** et ne jamais se fier à la liste figée à la création de la campagne.

## Envoi SMS (étape 4)

```
SmsProvider (interface)  ←  FakeSmsProvider (défaut, aucun réseau)  |  futur fournisseur réel (SmsManager::extend)
SmsManager  : résout le fournisseur ; un fournisseur « live » n'est JAMAIS instancié sans SMS_SENDING_ENABLED=true
SmsDispatcher::dispatch(contact, texte, clé d'idempotence) : seul point d'envoi
sms_messages : journal (UNIQUE company_id + idempotency_key), simulated=true tant que le fournisseur n'est pas réel
```

Garanties du répartiteur, dans l'ordre :
1. verrou d'activation (rien n'est écrit ni envoyé si un fournisseur réel n'est pas activé) ;
2. insertion idempotente (`INSERT … ON CONFLICT DO NOTHING`) : une clé = un seul message ;
3. **revérification du consentement et de la désinscription** (`SendEligibility`) → statut `blocked` sinon ;
4. prise en charge atomique `pending → sending` (un seul worker) ;
5. appel au fournisseur : refus définitif → `failed` ; erreur de transport → `unknown` (**jamais renvoyé automatiquement**, à réconcilier) ;
6. accusés de réception (`recordDeliveryReport`) : ne font jamais reculer un statut.

Les messages `simulated = true` ne doivent **jamais** être comptés comme des envois réels dans les rapports (étape 7).

## Crédits (étape 5)

Règle fournie (D2) : **100 crédits = 120 SMS**, 1 SMS = 1 segment. Les montants sont des **unités entières** :
1 crédit = 6 000 unités, 1 segment au tarif par défaut = 5 000 unités (5/6 de crédit, exact : 120 envois = 100 crédits pile).

```
companies.credit_balance_units          (CHECK >= 0 sur PostgreSQL)
credit_ledger_entries(company_id, type credit|debit|refund|adjustment, amount_units signé, balance_after_units,
                      idempotency_key, sms_message_id?, reason, simulated, created_by, created_at)
    UNIQUE(company_id, idempotency_key) ; AJOUT SEUL (modification/suppression refusées par le modèle)
sms_rates(country, prefix?, label, units_per_segment, is_active)      -- tarifs globaux, saisis par l'admin plateforme
sms_messages += segments, encoding, cost_units
```

- **Coût** = segments × tarif. Segments : norme GSM-7 (160/153) ou UCS-2 (70/67) ; un seul caractère hors GSM-7 (« ç », « â », emoji…) bascule tout le message en UCS-2.
  Tarif : préfixe d'opérateur le plus long > tarif du pays > tarif par défaut. **Aucun préfixe ni tarif n'est pré-rempli.**
- `CreditService` est le seul code qui modifie un solde : verrou de ligne, `UPDATE … WHERE solde >= montant` (jamais négatif),
  écriture au grand livre dans la même transaction, **une clé d'idempotence = une seule écriture**, un remboursement par débit.
- Envoi : débit (`sms:{id}`) **avant** l'appel au fournisseur ; solde insuffisant → message bloqué `insufficient_credits`, rien n'est envoyé ;
  refus définitif → remboursement ; erreur de transport → pas de remboursement (le message a peut-être été envoyé), réconciliation manuelle (correction admin).
- Les écritures liées à un envoi fictif sont marquées `simulated` (les rapports de l'étape 7 ne doivent pas les présenter comme des envois réels).
- `php artisan credits:verify` : contrôle en lecture seule solde = somme du grand livre.
- Administration plateforme (`is_platform_admin`) : `/admin/entreprises` (ajout/retrait avec motif obligatoire, double clic sans effet), `/admin/tarifs`.

## Garde-fous anti-abus de l'envoi réel (D12)

Pas d'approbation manuelle : chaque entreprise démarre en `probation`, puis est **validée ou suspendue automatiquement d'après ses envois réels**.
Les garde-fous ne s'appliquent **qu'aux envois réels** ; la simulation n'est jamais limitée et ne valide jamais une entreprise.

| Statut | Effet |
|---|---|
| `probation` (défaut) | limite journalière réduite (`SMS_PROBATION_DAILY_LIMIT`, 100) ; évaluation à chaque envoi réel |
| `validated` | plafond journalier configurable (`SMS_VALIDATED_DAILY_LIMIT`, aucun par défaut) |
| `suspended` | aucun envoi réel (statut `blocked`, motif `suspended`) ; la simulation reste possible |

- Évaluation (`SendingPolicy::evaluate`) dès que `min_sample` (50) envois réels sont atteints : taux d'échec > 20 % ou désinscriptions/envois > 5 % → **suspension automatique** ; sinon → **validation automatique**.
  Indicateurs calculés sur les envois réels uniquement (envois bloqués et simulés exclus).
- L'administrateur de la plateforme peut forcer un statut et une limite par entreprise (motif obligatoire) dans `/admin/entreprises`.
- Messages bloqués (`daily_limit`, `suspended`) : aucun débit, aucun appel au fournisseur.
- **Limites connues** : limite journalière « souple » (en concurrence, un léger dépassement est possible) ; une entreprise validée n'est pas réévaluée automatiquement (surveillance continue prévue à l'étape 9) ;
  les désinscriptions sont rapprochées des envois par numéro, à titre indicatif.
- Le débit de crédits reste un second frein : l'administrateur ne crédite que les entreprises qu'il choisit.

## Campagnes et file d'attente (étape 6)

```
campaigns(company_id, name, body, contact_group_id?, status, scheduled_at, recipient_count, excluded_count, pause_code/message, …)
campaign_messages(company_id, campaign_id, contact_id, phone, sms_message_id?, attempt, processed_at, note)
    UNIQUE(campaign_id, contact_id) ; FK composite (campaign_id, company_id)
```

```
draft → scheduled → preparing → sending → completed        (paused ↔ sending ; tout état non terminal → cancelled)
```

- **Planification** : `campaigns:dispatch-due` toutes les minutes (planificateur : `php artisan schedule:work` ou cron) ; au lancement, démarrage immédiat si l'heure est atteinte.
  Passage `scheduled → preparing` par compare-and-set : un seul acteur obtient la campagne.
- **PrepareCampaign** (file `sms`, unique par campagne) : fige les destinataires **ciblables à cet instant** (`INSERT … ON CONFLICT DO NOTHING`), compte les exclus, met un job par destinataire en file.
- **SendCampaignMessage** (un job par destinataire, 5 essais, délais croissants) : passe **toujours par `SmsDispatcher`** → consentement et désinscription revérifiés à l'envoi,
  garde-fous D12, débit idempotent. Clé d'idempotence déterministe `campaign:{id}:contact:{id}` (+ `:r{n}` après un blocage temporaire).
- **Blocages temporaires** (`insufficient_credits`, `daily_limit`, `suspended`, fournisseur non activé) : la campagne passe **en pause** (aucun destinataire perdu) ;
  la reprise est manuelle, sauf `daily_limit` (reprise automatique le lendemain). Les tentatives bloquées restent au journal ; un seul débit par message réellement envoyé.
- **Pause / annulation** : effet immédiat (les jobs en file ne font plus rien).
- **Contexte d'entreprise dans les jobs** : fixé explicitement par `CurrentCompany::runAs()` puis **restauré** (file synchrone : un job imbriqué n'écrase pas le contexte de l'appelant).
- **Chiffres des campagnes** : uniquement issus du journal des messages ; « délivré » dépend des accusés du fournisseur (aucun en simulation) ; badge « simulation » si des messages sont fictifs.
- Rôles : tout membre crée/modifie un brouillon ; owner/admin lancent, pausent, reprennent, annulent.

Exploitation : `php artisan queue:work redis --queue=sms` (plusieurs workers possibles) + `php artisan schedule:work`. Vérifié avec Redis réel et 4 workers en parallèle (voir le journal de l'étape).

## Rapports (étape 7)

Page `/rapports` (`App\Livewire\Reports\Index`, tous rôles : agrégats) et export `/rapports/export` (`ReportExportController`, owner/admin). Code métier dans `app/Domain/Reports`.

```
ReportFilters       période (jours civils du fuseau de l'application, fin incluse), campagne facultative, mode real|simulated ; validation unique
ReportService       agrégats GROUP BY (messages, grand livre), série journalière, requête d'export
ReportSummary       totaux d'UN bloc (réel OU simulé) ; aucune méthode n'additionne deux blocs
MessageCsvExporter  CSV streamé (lots par id), BOM UTF-8, séparateur configurable ; CsvCell = neutralisation des formules
config/reports.php  période par défaut, période maximale, plafond de lignes, séparateur (variables REPORTS_*)
```

- **Sources uniques** : `sms_messages` et `credit_ledger_entries` de l'entreprise active. Les requêtes sont des `DB::table(...)` avec `company_id` **explicite** (l'appelant passe l'id de l'entreprise active) et `simulated` **toujours filtré** : un bloc ne contient jamais l'autre. La campagne d'un filtre doit appartenir à l'entreprise active (règle de validation `exists … where company_id`), sinon « Campagne introuvable » (page) ou 404 (export).
- **Séparation stricte** : onglets « Envois réels » / « Simulation » ; bandeau « Simulation : aucun SMS réel n'a été envoyé » ; aucun total cumulé ; état vide « Aucune donnée sur cette période » (pas de zéros) ; taux jamais calculés sur un dénominateur nul. « Délivrés » dépend des accusés du fournisseur (le dit l'interface).
- **Fuseaux** : Laravel stocke et relit `created_at` en heure locale de l'application (jamais UTC). Les bornes sont donc comparées telles quelles : `created_at >= « début 00:00:00 » AND created_at < « lendemain de la fin 00:00:00 »`, et `DATE(created_at)` regroupe par jour civil local. Aucune conversion de fuseau n'est faite (D20). Testé en UTC, Africa/Kinshasa et Pacific/Auckland.
- **Campagne** : pas de colonne `campaign_id` sur `sms_messages` ; rattachement par le préfixe de clé d'idempotence `campaign:{id}:` (D21), ce qui inclut les tentatives bloquées rejouées. Lien « Voir le rapport détaillé » depuis la fiche campagne.
- **Crédits** : consommés = −(débits + remboursements) du grand livre de l'onglet (`credit_ledger_entries.simulated`) ; cohérent avec `credits:verify` (solde = ajouts − consommation nette ± corrections), testé.
- **Export** : lignes lues par lots (`lazyById`, `REPORTS_EXPORT_MAX_ROWS` max, refus au-delà), coût net par sous-requête sur le grand livre ; colonnes : date, numéro, campagne, statut, motif de blocage, segments, coût en crédits, simulé. **Injection de formules** : toute cellule (numéros compris) commençant par `= + - @ \t \r` est préfixée d'une apostrophe (D25). Rôle vérifié à chaque requête ; une entreprise ne voit jamais les données d'une autre ; le statut `is_platform_admin` n'ouvre rien.
- **Index** (migration `2026_11_15_100001`) : `sms_messages(company_id, simulated, created_at)` et `credit_ledger_entries(company_id, simulated, created_at)`. Les requêtes filtrent toutes sur ces trois colonnes ; sans `simulated` dans l'index, un bloc devrait lire les lignes de l'autre pour les écarter. Les index existants `(company_id, created_at)` ne sont pas modifiés.
- **Limites connues** : le filtre campagne utilise `LIKE 'campaign:{id}:%'` (pas de gain d'index sur ce motif, la plage de dates borne déjà la lecture) ; la lecture `COUNT` puis export n'est pas un instantané unique (des messages créés entre les deux peuvent dépasser légèrement le plafond) ; pas de journal d'audit des exports (étape 9).
## Webhooks et fournisseurs (étape 10 préparatoire)

Infrastructure **indépendante du fournisseur** (le fournisseur réel n'est pas choisi : D1). Aucun adaptateur réel, aucun appel réseau, aucun format de webhook
réel : tout est validé avec le `FakeSmsProvider` et un format de webhook **fictif** propre à la simulation. Guide d'écriture d'un adaptateur : `docs/PROVIDERS.md`.

```
POST /webhooks/sms/{provider}      (hors session, hors CSRF, JSON, limité en débit : throttle sms-webhooks)
  SmsWebhookController  (générique, fail-closed)
    SmsWebhookRegistry  → SmsWebhookVerifier du fournisseur (verify : signature + horodatage ; parse : corps → SmsWebhookEvent)
    webhook_events      → déduplication UNIQUE (provider, event_id), dans la même transaction que le traitement
    SmsWebhookProcessor → accusé : SmsDispatcher::recordDeliveryReport ; entrant : mot-clé → ConsentService::optOut sous CurrentCompany::runAs()
```

- **Fail-closed** : fournisseur sans vérificateur → 404 ; secret absent (`SMS_WEBHOOK_FAKE_SECRET` vide par défaut) → 401 pour toute requête ; signature
  absente/invalide, mauvais secret, horodatage hors fenêtre (passé OU futur, `SMS_WEBHOOK_TOLERANCE_SECONDS`) → 401 ; corps > `SMS_WEBHOOK_MAX_BODY_BYTES` → 413.
  Comparaison de signature en temps constant (`hash_equals`) sur le corps brut. Plusieurs couches vérifient la présence du secret (contrôleur, vérificateur, `HmacSignature`) : recouvrement voulu.
- **Déduplication** : un événement rejoué (même `provider` + `event_id`) est acquitté en 200 `duplicate` sans effet. Si le traitement échoue (500), l'enregistrement est
  annulé avec la transaction : le fournisseur peut rejouer. `webhook_events` ne contient que l'identité de l'événement (ni corps, ni numéro, ni texte). Table technique
  globale, sans `company_id` (un webhook n'a pas de contexte d'entreprise) ; pas de purge automatique (à prévoir avec D9, rétention).
- **Accusés de réception** : `recordDeliveryReport` inchangé (ne fait jamais reculer un statut ; message inconnu ou d'un autre fournisseur → ignoré, événement acquitté).
- **Messages entrants** : seul un texte égal à un mot-clé (`SMS_INBOUND_OPTOUT_KEYWORDS`, défaut `STOP`, à valider D4/D14 ; casse, accents, espaces et ponctuation de bord ignorés ;
  « please stop » ne correspond pas) a un effet. Les autres messages sont ignorés et **leur contenu n'est jamais stocké** (données personnelles).
- **Règle de désinscription et son ambiguïté** : un SMS entrant ne dit pas à quelle entreprise il s'adresse (un numéro peut avoir été contacté par plusieurs). Le numéro est
  désinscrit dans **chaque entreprise ayant envoyé un message RÉEL** (`simulated = false`, statut envoyé, délivré ou inconnu — pas bloqué/échoué/en attente) à ce numéro dans les
  `SMS_INBOUND_WINDOW_DAYS` derniers jours (30). Conséquence assumée : un « STOP » destiné à l'entreprise X coupe aussi Y si Y a écrit au même numéro dans la fenêtre
  (choix prudent côté consentement). Les entreprises sans envoi récent et les messages simulés ne sont jamais touchés. Idempotent (une seule désinscription active par entreprise et numéro).
- **Réconciliation** : `php artisan sms:reconcile` (lecture seule) liste par entreprise les messages `sending`/`unknown` depuis plus de `SMS_RECONCILE_STALE_MINUTES` (60),
  numéros masqués (`+243*******01`), sans contenu ; simulés exclus sauf `--include-simulated` (section séparée, sans effet sur le code de sortie). Sortie : 0 = rien, 1 = au moins un
  message réel, 2 = option invalide. La résolution automatique/remboursement dépend de D13 : **non implémentée** (procédure manuelle dans `docs/PROVIDERS.md`).
- **Contrats de test** réutilisables : `Tests\Contracts\SmsProviderContractTest` et `Tests\Contracts\SmsWebhookVerifierContractTest`.

## Cible (étapes suivantes)

```
```

## Flux d'envoi cible

```
Campagne planifiée → Job "PrepareCampaign" (fige les destinataires)
  → pour chaque message : Job "SendMessage" (file Redis)
       1. verrou + statut atomique (pending → sending)
       2. re-vérifier consentement + désinscription
       3. débit idempotent (clé = message_id)
       4. SmsProvider::send()  (Fake par défaut)
       5. enregistrer résultat ; rembourser si échec définitif (écriture inverse)
```
