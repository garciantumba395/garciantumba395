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

## Cible (étapes suivantes)

```
campaigns, campaign_messages (UNIQUE campaign_id+contact_id)
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
