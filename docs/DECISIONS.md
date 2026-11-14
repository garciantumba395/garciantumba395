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
