# Décisions à prendre

Rien ci-dessous n'est supposé : ces points conditionnent les étapes indiquées.

| # | Décision | Impact | Besoin avant |
|---|---|---|---|
| D1 | Fournisseur SMS réel (agrégateur, opérateur direct, passerelle locale). L'interface `SmsProvider` est prête : il suffira d'écrire un adaptateur | contrat, API, DLR, coût | Étape 10 |
| D2 | ✅ **Tranchée (règle fournie)** : 100 crédits = 120 SMS (1 SMS = 1 segment, facturation par segment) ; coût variable par pays/opérateur via la table des tarifs (vide : le tarif par défaut s'applique) ; seul l'administrateur de la plateforme ajoute des crédits. **À confirmer** : facturation par segment pour les messages longs, remboursement sur accusé « échec » tardif (D13), prix du crédit en argent (D7) | modèle des crédits | — |
| D3 | Sender ID : enregistrement/validation par opérateur ? | champs campagne | Étape 6 |
| D4 | Cadre légal local (consentement, désinscription, heures d'envoi, protection des données) : **à faire valider par un juriste**. Mécanisme technique livré à l'étape 3 et configurable (`config/consent.php`) : sources, preuve obligatoire (oui par défaut), durée de validité (aucune par défaut). **Aucune règle légale n'est supposée.** Restent à définir : durée de validité, heures d'envoi, mention de désinscription dans les SMS, durée de conservation des preuves | règles | Étape 6 (envoi) |
| D5 | ✅ **Tranchée : inscription ouverte** (validation manuelle / anti-abus à reconsidérer à l'étape 9) | anti-abus | — |
| D6 | RLS PostgreSQL en plus du scope applicatif | robustesse isolation | Étape 3 |
| D7 | Moyens de paiement / recharge de crédits (manuel d'abord ?) | facturation | Étape 5 |
| D8 | ✅ **Pays et indicatif par entreprise** (défaut RDC `CD`, +243), numéros stockés en E.164 ; fuseau `Africa/Kinshasa` par défaut (`APP_TIMEZONE`). Devise d'affichage : en attente de D2 | formats | — |
| D9 | Hébergement, sauvegardes, rétention des données | exploitation | Étape 9 |
| D10 | Vérification e-mail obligatoire ? 2FA pour owners/admins ? | sécurité | Étape 9 |
| D14 | Mention de désinscription dans les messages de campagne (ex. « STOP au … ») : dépend du cadre légal (D4) et des SMS entrants (D1) ; **non ajoutée automatiquement**. Ciblage par un seul groupe ou tous les contacts (plusieurs groupes : à voir) | conformité, UX | Étape 10 |
| D11 | Volumes attendus (SMS/jour, contacts/entreprise) | dimensionnement | Étape 6 |
| D12 | ✅ **Tranchée** : pas d'approbation manuelle avant l'envoi réel ; validation **automatique après les envois** (garde-fous choisis par moi, à ajuster : voir ARCHITECTURE « Garde-fous anti-abus »). Seuils par défaut (démarrage 100 envois réels/jour, échantillon 50, échecs ≤ 20 %, désinscriptions ≤ 5 %) : **valeurs techniques prudentes, à ajuster selon D11 et le fournisseur** | anti-abus | — |
| D13 | Remboursement des crédits quand l'accusé de réception final est « échec » (après acceptation par le fournisseur) : non remboursé aujourd'hui | modèle économique | Étape 10 |
| D30 | Journal d'audit (étape 8) : en **ajout seul**, sans secret/mot de passe/message/numéro (clés interdites refusées, numéros et e-mails masqués dans les textes libres), **non rattaché à une entreprise** (journal de plateforme). **À valider** : durée de conservation, export éventuel, purge (aucune aujourd'hui : l'ajout seul interdit la suppression par l'application), accès des entreprises à leur propre historique | exploitation, vie privée | Étape 9 |
| D31 | Désactivation d'un compte : déconnexion à la **prochaine requête** (middleware) + suppression des sessions `database` ; pas de notification à l'utilisateur ; connexion refusée avec un message qui ne distingue pas « mot de passe faux » et « compte désactivé » (pas d'énumération). **À valider** : message explicite, motif communiqué ou non | UX, sécurité | Étape 9 |
| D32 | Dernier administrateur : on compte seulement les administrateurs de plateforme **actifs** (non désactivés) ; refus de désactiver/révoquer le dernier ; auto-désactivation interdite ; l'attribution du statut reste **en console uniquement**. **À valider** : exiger deux administrateurs minimum ? double validation (4 yeux) pour les actions sensibles ? | gouvernance | Étape 9 |
| D33 | Définitions du tableau de bord admin (choix techniques) : « messages » = lignes du journal `sms_messages` (tous statuts, y compris bloqués), réels et simulés comptés **séparément** ; « campagnes actives » = programmées + en préparation + en envoi (les pauses sont indiquées à part) ; « crédits en circulation » = somme des soldes des entreprises ; « jobs en échec » = table `failed_jobs`. **À valider** | pilotage | — |
| D34 | Confidentialité côté administrateur : il voit les **comptes** des membres (nom, e-mail de connexion, rôle) et des agrégats, jamais contacts, numéros, textes, consentements ni noms de campagne. **À valider** juridiquement (le nom/e-mail des utilisateurs est une donnée personnelle ; base légale et information des utilisateurs) | conformité (D4) | Étape 9 |
| D35 | Désactiver une entreprise : conserve toutes les données ; ses campagnes en préparation/en envoi sont **mises en pause** (`company_disabled`), les programmées ne démarrent plus ; les messages déjà passés au fournisseur ne sont pas rappelables. **À valider** : suppression/anonymisation à terme, durée de rétention | exploitation | Étape 9 |

