# Environnement de recette Solisend (à faire AVANT d'acheter l'hébergement)

Objectif : vérifier que tout fonctionne avec des **données fictives** et un **fournisseur SMS simulé** (aucun SMS réel, aucune clé, aucun coût).

## Trois façons de tester

| Option | Pour qui | Coût | Mise en route |
|---|---|---|---|
| **A. Démo dans la session Claude** | Voir tout de suite | 0 | Aperçu « Solisend démo » dans l'application Claude (disparaît à la fin de la session) |
| **B. Docker sur votre ordinateur** | Recette durable, e-mails inclus | 0 | `docker compose -f docker-compose.demo.yml up --build` puis http://localhost:8000 (e-mails : http://localhost:8025) |
| **C. Installation locale sans Docker** | Développeur | 0 | `bash scripts/setup-local.sh` puis `php artisan db:seed --class=DemoSeeder` et `php artisan serve` |

Le mot de passe des comptes démo est généré au hasard et affiché **une seule fois** au premier démarrage (`docker compose -f docker-compose.demo.yml logs web`). Comptes : `admin-demo@example.test` (plateforme), `proprio-demo@example.test` (propriétaire de « Boutique Démo »), `membre-demo@example.test` (membre), `autre-demo@example.test` (autre entreprise, pour vérifier l'isolation).

> L'option B n'a pas pu être exécutée dans l'environnement de développement (pas de Docker) : premier lancement à confirmer sur votre machine ; en cas d'erreur de construction, envoyez-moi la sortie.

## Parcours à dérouler (cocher)

| # | Parcours | Résultat attendu |
|---|---|---|
| 1 | Connexion propriétaire, tableau de bord | Chiffres cohérents, logo Solisend, nom de l'entreprise |
| 2 | Contacts : recherche, import CSV (fichier fictif), numéros invalides | Rapport d'import sans données personnelles ; numéros invalides listés par ligne |
| 3 | Consentement : un contact sans consentement | Jamais éligible à l'envoi |
| 4 | Campagne : estimation, lancement (simulé) | Bandeau « simulation » ; texte final avec « Publicité - … Désabonnement: … » ; coût en crédits cohérent |
| 5 | Messages et rapports | Onglets Réel / Simulation séparés ; export CSV |
| 6 | Lien de désinscription (page publique `/d/…`) | La page n'agit pas seule ; le bouton confirme ; le numéro apparaît dans « Désinscriptions » ; une 2e campagne l'exclut |
| 7 | Crédits : solde insuffisant | Rien n'est envoyé, campagne en pause puis reprise après recharge par l'administrateur |
| 8 | Administrateur plateforme : ajout de crédits, tarifs, audit | Chaque action apparaît dans le journal d'audit |
| 9 | Isolation : connecté en `autre-demo`, chercher les données de « Boutique Démo » | Aucune donnée visible, URL directes refusées |
| 10 | Inscription d'une nouvelle entreprise avec `SECURITY_REQUIRE_*=true` | E-mail de vérification (visible dans Mailpit), puis 2FA obligatoire (application d'authentification) |
| 11 | PWA : « Installer l'application » dans Chrome/Edge, puis couper le réseau | Page « Vous êtes hors connexion », aucune donnée conservée |
| 12 | Téléphone : mêmes parcours en largeur étroite | Pas de défilement horizontal, boutons accessibles |

## Ce que cette recette ne peut PAS prouver

- La **livraison réelle** des SMS, les accusés de réception, le mot-clé « STOP » et l'accusé de désinscription par SMS : nécessitent le compte Infobip (sandbox), voir `docs/PROVIDERS.md`.
- La **tenue en charge** et la configuration HTTPS/SMTP de production : voir `docs/OPERATIONS.md` (liste de contrôle de déploiement, `php artisan security:check`).
- La **conformité juridique** : avis d'un juriste (`docs/CADRE_LEGAL_RDC.md`).

## Décision d'achat

Si les 12 parcours sont validés, vous pouvez acheter l'hébergement. Prérequis à demander à l'hébergeur : PHP 8.3+, PostgreSQL 16, Redis, HTTPS, un processus permanent pour le worker (`queue:work`) et la tâche planifiée (`schedule:run`), SMTP.
