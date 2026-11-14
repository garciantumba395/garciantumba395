# Décisions à prendre

Rien ci-dessous n'est supposé : ces points conditionnent les étapes indiquées.

| # | Décision | Impact | Besoin avant |
|---|---|---|---|
| D1 | Fournisseur SMS réel (agrégateur, opérateur direct, passerelle locale). L'interface `SmsProvider` est prête : il suffira d'écrire un adaptateur | contrat, API, DLR, coût | Étape 10 |
| D2 | Tarification SMS et unités de crédit (par SMS, par segment, par opérateur ?) | modèle des crédits | Étape 5 |
| D3 | Sender ID : enregistrement/validation par opérateur ? | champs campagne | Étape 6 |
| D4 | Cadre légal local (consentement, désinscription, heures d'envoi, protection des données) : **à faire valider par un juriste**. Mécanisme technique livré à l'étape 3 et configurable (`config/consent.php`) : sources, preuve obligatoire (oui par défaut), durée de validité (aucune par défaut). **Aucune règle légale n'est supposée.** Restent à définir : durée de validité, heures d'envoi, mention de désinscription dans les SMS, durée de conservation des preuves | règles | Étape 6 (envoi) |
| D5 | ✅ **Tranchée : inscription ouverte** (validation manuelle / anti-abus à reconsidérer à l'étape 9) | anti-abus | — |
| D6 | RLS PostgreSQL en plus du scope applicatif | robustesse isolation | Étape 3 |
| D7 | Moyens de paiement / recharge de crédits (manuel d'abord ?) | facturation | Étape 5 |
| D8 | ✅ **Pays et indicatif par entreprise** (défaut RDC `CD`, +243), numéros stockés en E.164 ; fuseau `Africa/Kinshasa` par défaut (`APP_TIMEZONE`). Devise d'affichage : en attente de D2 | formats | — |
| D9 | Hébergement, sauvegardes, rétention des données | exploitation | Étape 9 |
| D10 | Vérification e-mail obligatoire ? 2FA pour owners/admins ? | sécurité | Étape 9 |
| D11 | Volumes attendus (SMS/jour, contacts/entreprise) | dimensionnement | Étape 6 |
| D12 | Activation de l'envoi réel **par entreprise** (validation par la plateforme avant d'autoriser le réel), en plus de l'interrupteur global | anti-abus, conformité | Étape 8 |
