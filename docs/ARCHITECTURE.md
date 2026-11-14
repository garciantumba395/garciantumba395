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

À l'étape 5, le débit de crédits s'insérera avant l'étape 5 ci-dessus, avec la même clé d'idempotence.
Les messages `simulated = true` ne doivent **jamais** être comptés comme des envois réels dans les rapports (étape 7).

## Cible (étapes suivantes)

```
campaigns, campaign_messages (UNIQUE campaign_id+contact_id)
credit_ledger_entries (append-only, UNIQUE idempotency_key), credit_balances (cache)
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
