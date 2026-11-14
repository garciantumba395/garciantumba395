# Plan de réalisation

| Étape | Contenu | Critères de sortie |
|---|---|---|
| **1 (faite)** | Socle : auth, entreprises, rôles, isolation (trait + tests), doc | `php artisan test` vert ; inscription → entreprise créée |
| **2 (faite)** | Contacts, groupes, import CSV (validation E.164, doublons, rapport d'import) | import idempotent ; tests d'isolation |
| **3 (faite)** | Consentement (preuve : source, date, texte, IP/utilisateur) + désinscriptions ; décision RLS | impossible d'ajouter un contact ciblable sans statut de consentement |
| **4 (faite)** | Interface `SmsProvider` + `FakeSmsProvider` + journal de messages | aucun appel réseau ; activation explicite testée |
| **5 (faite)** | Grand livre de crédits (ajout manuel par admin, débit idempotent) | pas de solde négatif ; double débit impossible |
| **6 (faite)** | Campagnes + envoi en file Redis (Horizon optionnel) | rejeu d'un job sans double envoi/débit |
| 7 | Rapports d'envoi (chiffres issus uniquement des données réelles) | export CSV ; aucun chiffre fictif |
| **8 (faite)** | Administration plateforme (entreprises, crédits, audit) | journal d'audit en ajout seul ; pages admin ; commandes console |
| 9 | Durcissement : 2FA, limitation de débit, sauvegardes, observabilité | revue sécurité |
| 10 | Intégration d'un fournisseur réel (après décisions D1–D3) | recette en sandbox du fournisseur |

Chaque étape : migrations + tests (dont isolation) + mise à jour de la doc.
