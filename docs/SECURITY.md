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

## Étape 9 — durcissement

- **2FA TOTP optionnelle** (`/profil`) : secret et codes de secours chiffrés en base (cast `encrypted`), codes de secours stockés sous forme de HMAC-SHA256 et à usage unique, anti-rejeu (un pas TOTP déjà accepté est refusé, mise à jour conditionnelle en base), défi à la connexion limité en débit, session non ouverte avant le défi. Désactivation : mot de passe + code. Obligation configurable pour les administrateurs (`SECURITY_REQUIRE_2FA_FOR_ADMINS`, défaut `false`, D40).
- Changement de mot de passe : mot de passe actuel exigé, politique ≥ 10 caractères, autres sessions invalidées (`AuthenticateSession`).
- **Limitation de débit** nommée et configurable (D41) : connexion, inscription, mot de passe oublié, défi 2FA, actions sensibles du profil, import CSV, envoi de test, actions d'administration, plafond global Livewire (HTTP 429).
- **En-têtes** : `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS (HTTPS seulement), CSP partielle sans `script-src` (D42, raison dans `docs/ARCHITECTURE.md`).
- `php artisan security:check` : lecture seule, n'affiche jamais de valeur secrète, code de sortie ≠ 0 en cas de réglage dangereux.
- Entreprises validées surveillées en continu (`sending:review`, D43) ; échec de `credits:verify` journalisé sans donnée.
- Exploitation, sauvegardes, interrupteur d'urgence, incident, rotation des clés : `docs/OPERATIONS.md`.
- **Limites** : pas de vérification d'e-mail (D10) ; pas de QR code ni de clé matérielle (WebAuthn) ; pas de CSP `script-src` ; la limitation de débit dépend d'un cache partagé (Redis) en multi-processus ; le défi 2FA est limité par utilisateur (verrouillage temporaire possible par quelqu'un connaissant le mot de passe).
