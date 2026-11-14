# Règles de sécurité du dépôt

- Aucun secret ni donnée client dans Git : `.env*` (sauf `.env.example`), `storage/`, exports CSV sont ignorés.
- `.env.example` ne contient que des valeurs vides ou fictives.
- Aucun envoi SMS réel sans activation explicite (futur : `SMS_SENDING_ENABLED=true` **et** fournisseur réel configuré ; défaut : Fake).
- `is_platform_admin` jamais assignable en masse ; attribué uniquement en console.
- Les identifiants venant du client (ID Livewire, session) ne sont jamais crus : revérification contre les adhésions.
- Mots de passe : 10 caractères minimum ; connexion limitée à 5 essais ; réponse « mot de passe oublié » non énumérante.
- Les mots de passe de développement local ne doivent jamais être réutilisés en production.
