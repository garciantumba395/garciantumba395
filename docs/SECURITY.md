# Règles de sécurité du dépôt

- Aucun secret ni donnée client dans Git : `.env*` (sauf `.env.example`), `storage/`, exports CSV sont ignorés.
- `.env.example` ne contient que des valeurs vides ou fictives.
- Aucun envoi SMS réel sans activation explicite : `SMS_PROVIDER=<fournisseur réel>` **et** `SMS_SENDING_ENABLED=true`. Défaut : fournisseur fictif, `SMS_SENDING_ENABLED=false`. Sans le second réglage, un fournisseur réel ne peut même pas être instancié. Les clés d'API du fournisseur iront uniquement dans le `.env` du serveur.
- Envoi réel : pas d'approbation manuelle mais démarrage limité et validation/suspension automatiques (`SendingPolicy`) ; `sending_status` et `daily_send_limit` ne sont jamais assignables en masse.
- Un résultat d'envoi inconnu (erreur de transport) n'est jamais renvoyé automatiquement.
- `is_platform_admin` jamais assignable en masse ; attribué uniquement en console.
- Les identifiants venant du client (ID Livewire, session) ne sont jamais crus : revérification contre les adhésions.
- Mots de passe : 10 caractères minimum ; connexion limitée à 5 essais ; réponse « mot de passe oublié » non énumérante.
- Les mots de passe de développement local ne doivent jamais être réutilisés en production.
