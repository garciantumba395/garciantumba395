# Exploitation (étape 9)

Document **générique** : il ne suppose aucun hébergeur ni outil de supervision précis. Les valeurs chiffrées (rétention, fréquences) sont des
**exemples à valider** (décisions D9 et D44 de `docs/DECISIONS.md`). Aucune valeur secrète ne figure ici : les secrets vivent uniquement dans le
`.env` du serveur (droits `600`, propriétaire = utilisateur applicatif), jamais dans Git, jamais dans un ticket ou un message.

## 1. Liste de contrôle de déploiement

Avant la première mise en production et à chaque déploiement :

1. `.env` de production créé à partir de `.env.example` ; `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`.
2. `php artisan key:generate` **une seule fois** (voir §9 : changer `APP_KEY` plus tard invalide les sessions, les secrets 2FA et les codes de secours).
3. Mot de passe PostgreSQL fort et unique (jamais réutilisé depuis le développement) ; Redis non exposé sur Internet (écoute locale ou réseau privé, mot de passe si le réseau n'est pas de confiance).
4. `composer install --no-dev --optimize-autoloader` puis `npm ci && npm run build`.
5. **`php artisan security:check`** : doit se terminer par « Aucun problème bloquant » (code de sortie 0). Utiliser `--strict` pour traiter aussi les avertissements comme bloquants.
6. `php artisan migrate --force` (après une **sauvegarde**, voir §5).
7. `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache`.
8. `php artisan queue:restart` (les workers rechargent le code et la configuration) puis vérifier que les processus du §3 tournent.
9. Vérifier `GET /up` (HTTP 200) et la page de connexion en HTTPS (en-têtes : `curl -sI https://…/connexion`).
10. **Par défaut aucun SMS réel ne part** (`SMS_PROVIDER=fake`, `SMS_SENDING_ENABLED=false`). L'activation de l'envoi réel est une décision explicite distincte (étape 10, décisions D1–D3).
11. Planificateur actif (§3) : `php artisan schedule:list` doit montrer `campaigns:dispatch-due`, `sending:review` et `credits:verify`.
12. Le proxy/serveur web doit transmettre `X-Forwarded-Proto` et `TRUSTED_PROXIES` doit désigner ce proxy, sinon l'application se croit en HTTP (pas de HSTS, URLs en `http://`).

## 2. Variables d'environnement (noms uniquement)

| Variable | Rôle | Remarque |
|---|---|---|
| `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_TIMEZONE`, `APP_LOCALE` | Application | `APP_DEBUG=false` et `APP_ENV=production` en production ; `APP_TIMEZONE=Africa/Kinshasa` |
| `TRUSTED_PROXIES` | Proxys de confiance | requis derrière un proxy/répartiteur |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL | `DB_PASSWORD` **secret** |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Redis | `REDIS_PASSWORD` **secret** si utilisé |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | Pilotes | `redis` attendu ; `sync`/`array` signalés par `security:check` |
| `SESSION_SECURE_COOKIE`, `SESSION_LIFETIME`, `SESSION_SAME_SITE`, `SESSION_DOMAIN` | Cookies | `SESSION_SECURE_COOKIE=true` obligatoire en HTTPS |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | E-mail (réinitialisation de mot de passe) | `MAIL_PASSWORD` **secret** ; `log` en production = e-mails jamais envoyés |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | Journaux | éviter `debug` en production |
| `SMS_PROVIDER`, `SMS_SENDING_ENABLED`, `SMS_QUEUE` | SMS | `fake` / `false` par défaut ; clés d'API du fournisseur (étape 10) = **secrets** |
| `SMS_PROBATION_DAILY_LIMIT`, `SMS_VALIDATED_DAILY_LIMIT` | Garde-fous (D12) | voir `config/sms.php` |
| `SECURITY_REQUIRE_2FA_FOR_ADMINS`, `SECURITY_TOTP_WINDOW`, `SECURITY_TOTP_ISSUER` | 2FA | voir D40 |
| `RATE_REGISTER_MAX`, `RATE_PASSWORD_FORGOT_MAX`, `RATE_2FA_MAX`, `RATE_PROFILE_MAX`, `RATE_CSV_IMPORT_MAX`, `RATE_TEST_SEND_MAX`, `RATE_ADMIN_MAX`, `RATE_LIVEWIRE_MAX` | Limites de débit | voir D41 ; défauts dans `config/security.php` |
| `SECURITY_HSTS`, `SECURITY_HSTS_MAX_AGE`, `SECURITY_HSTS_SUBDOMAINS`, `SECURITY_CSP`, `SECURITY_CSP_REPORT_ONLY` | En-têtes | voir D42 |
| `SENDING_REVIEW_WINDOW_DAYS`, `SENDING_REVIEW_MIN_SAMPLE` | Surveillance continue | voir D43 |

Les compteurs de limitation de débit utilisent le cache (`CACHE_STORE=redis` en production : sinon les compteurs ne sont pas partagés entre processus).

## 3. Processus à superviser

| Processus | Commande | Rôle |
|---|---|---|
| Serveur web + PHP-FPM | (selon votre serveur) | l'application |
| **Worker d'envoi** | `php artisan queue:work redis --queue=sms --tries=3 --max-time=3600` | envois SMS (campagnes, test). Plusieurs workers possibles |
| **Planificateur** | `php artisan schedule:work` **ou** une ligne cron `* * * * * cd /chemin/app && php artisan schedule:run >> /dev/null 2>&1` | `campaigns:dispatch-due` (chaque minute), `sending:review` (03:00), `credits:verify` (03:30) |
| PostgreSQL, Redis | services système | données ; file et cache |

`--max-time` fait quitter proprement le worker après une heure : le superviseur le relance (évite les fuites mémoire et recharge le code). Exemples **en texte**, à adapter (chemins, utilisateur) :

Fichier systemd `sms-worker.service` :

```
[Unit]
Description=Worker de file SMS
After=network.target redis.service postgresql.service

[Service]
User=app
WorkingDirectory=/chemin/vers/application
ExecStart=/usr/bin/php artisan queue:work redis --queue=sms --tries=3 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Fichier systemd `sms-scheduler.service` :

```
[Unit]
Description=Planificateur Laravel
After=network.target

[Service]
User=app
WorkingDirectory=/chemin/vers/application
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Équivalent supervisor (`/etc/supervisor/conf.d/sms.conf`) :

```
[program:sms-worker]
command=php artisan queue:work redis --queue=sms --tries=3 --max-time=3600
directory=/chemin/vers/application
user=app
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/log/sms-worker.log

[program:sms-scheduler]
command=php artisan schedule:work
directory=/chemin/vers/application
user=app
autostart=true
autorestart=true
```

Un seul planificateur doit tourner (`withoutOverlapping` protège contre le chevauchement d'une même tâche, pas contre plusieurs serveurs : si l'application est répliquée, ne lancer le planificateur que sur un nœud).

## 4. Redis

- La file `sms` et le cache y vivent. **Une perte de Redis perd les jobs en attente** (les campagnes concernées restent à l'état « Préparation/Envoi » sans progression) : activer la persistance (AOF `appendonly yes`, `appendfsync everysec` en exemple) et la surveiller ; décider de la politique de reprise (D44).
- Ne jamais exécuter `FLUSHALL`/`FLUSHDB` sur une instance de production sans avoir arrêté les workers et compris l'impact (jobs perdus, limites de débit remises à zéro).
- Mémoire : définir une limite (`maxmemory`) avec une politique qui **ne supprime pas** les clés de file (`noeviction`) ; surveiller la taille de la file.
- Le dossier `dump.rdb` éventuel appartient à Redis, pas à l'application : ne pas le versionner.

## 5. Sauvegardes PostgreSQL (D9 à valider)

Exemple de sauvegarde quotidienne (hors du serveur de base, chiffrée au repos selon votre politique) :

```
# /etc/cron.d/sms-backup  (le mot de passe vient de ~/.pgpass, droits 600, jamais dans la ligne de commande)
15 2 * * * app pg_dump --format=custom --file=/chemin/sauvegardes/sms_$(date +\%F).dump --host=127.0.0.1 --username=sms sms_saas
```

- **Rétention : à décider (D9).** Exemple de départ à valider : 14 sauvegardes quotidiennes + 8 hebdomadaires, copie hors site. Les données personnelles (numéros) sont présentes dans les sauvegardes : la rétention doit être cohérente avec la politique de conservation (D4).
- **Test de restauration obligatoire**, au moins avant la mise en production puis périodiquement : restaurer sur une base **vide et séparée** (jamais sur la production) :

```
createdb sms_restore_test
pg_restore --no-owner --dbname=sms_restore_test /chemin/sauvegardes/sms_AAAA-MM-JJ.dump
DB_DATABASE=sms_restore_test php artisan credits:verify      # doit répondre « OK »
dropdb sms_restore_test
```

- Sauvegarder aussi le `.env` (dans un coffre à secrets, **pas** avec les dumps) : sans `APP_KEY`, les secrets 2FA chiffrés en base sont illisibles.
- Avant chaque migration en production : une sauvegarde fraîche.

## 6. Surveillance

| Contrôle | Comment | Alerte si |
|---|---|---|
| Disponibilité | `GET /up` (sonde HTTP) | pas de 200 |
| Workers | processus `queue:work` actifs ; taille de la file Redis `sms` | processus absent, file qui ne décroît pas |
| Jobs en échec | `php artisan queue:failed` (table `failed_jobs`) | toute ligne nouvelle ; reprise manuelle : `php artisan queue:retry <id>` après analyse |
| Cohérence des crédits | `credits:verify` quotidien (03:30) ; échec journalisé en **erreur** (« credits:verify : N incohérence(s)… », sans donnée) | une ligne d'erreur dans les journaux |
| Dérive des entreprises validées | `sending:review` quotidien (03:00) ; chaque suspension journalisée en **avertissement** avec les seuls identifiants d'entreprise | une ligne d'avertissement : examiner l'entreprise dans `/admin/entreprises` |
| Configuration | `security:check` après chaque changement de `.env` | code de sortie ≠ 0 |
| Journaux | `storage/logs/` (ou le canal configuré) : vérifier à la mise en production qu'ils ne contiennent ni numéro ni texte de message (le code de l'étape 9 n'y écrit que des compteurs et des identifiants d'entreprise) | — |
| Disque | `storage/`, sauvegardes, journaux | espace faible |

Aucun outil de supervision n'est imposé : toute sonde HTTP + surveillance de processus + lecture des journaux convient.

## 7. Interrupteur d'urgence (arrêt des envois réels)

À utiliser en cas d'envois indésirables, de fuite de clé du fournisseur, d'incident chez le fournisseur ou de doute :

1. Mettre `SMS_SENDING_ENABLED=false` dans le `.env`.
2. Recharger la configuration : `php artisan config:cache` (ou `config:clear` si le cache n'est pas utilisé).
3. **Redémarrer les workers** : `php artisan queue:restart` (les workers gardent la configuration en mémoire jusqu'à leur redémarrage) ou redémarrer le service.
4. Vérifier : `php artisan security:check` (ligne `SMS_SENDING_ENABLED : désactivé`) et la page « Messages » (bandeau).

Effet (garanti par `SmsManager`, vérifié par les tests) : un fournisseur réel ne peut plus être instancié ; les campagnes en cours se **mettent en pause** (`provider_unavailable`) sans perdre de destinataire ni débiter de crédits ; elles pourront être reprises après réactivation. La simulation (fournisseur fictif) reste possible.
Suspendre **une** entreprise seulement : `/admin/entreprises` → statut d'envoi « suspendu » avec motif.
Si la clé du fournisseur est compromise : couper d'abord (étapes ci-dessus), puis la révoquer chez le fournisseur et en générer une nouvelle (§9).

## 8. Procédure d'incident (générique)

1. **Contenir** : interrupteur d'urgence (§7) si des SMS partent à tort ; suspendre l'entreprise concernée ; désactiver le compte suspect (`companies.is_active` / retrait de membre) ; si un compte est compromis : changer son mot de passe et utiliser « Déconnecter les autres appareils » dans `/profil`.
2. **Préserver** : copier les journaux et une sauvegarde de la base **avant** toute correction ; noter l'heure (fuseau Africa/Kinshasa) de chaque action.
3. **Évaluer** : quelles entreprises, quels envois (journal `sms_messages`, champ `simulated`), quels crédits (grand livre en ajout seul : `php artisan credits:verify`).
4. **Corriger** : cause racine ; corrections de crédits uniquement par écriture d'ajustement avec motif (jamais de modification directe du grand livre).
5. **Rétablir** progressivement : réactiver l'envoi, surveiller les journaux et la file.
6. **Rendre compte** : consigner l'incident et les mesures ; les obligations de notification éventuelles dépendent du cadre légal local (D4) — **à faire valider par un juriste**, rien n'est supposé ici.

## 9. Rotation des secrets

| Secret | Procédure | Conséquence |
|---|---|---|
| Mot de passe PostgreSQL | changer côté base, mettre à jour `DB_PASSWORD`, `config:cache`, `queue:restart` | brève indisponibilité |
| `REDIS_PASSWORD` | idem côté Redis et `.env` | idem |
| Clé d'API fournisseur SMS | générer une nouvelle clé chez le fournisseur, mettre à jour le `.env`, redémarrer les workers, révoquer l'ancienne | aucune si fait dans cet ordre |
| `MAIL_PASSWORD` | idem | — |
| **`APP_KEY`** | **impact fort** : invalide toutes les sessions, rend illisibles les secrets TOTP chiffrés (sauf via `APP_PREVIOUS_KEYS`) et invalide les codes de secours (HMAC dérivés de la clé). À éviter sauf compromission. Si nécessaire : déplacer l'ancienne clé dans `APP_PREVIOUS_KEYS` (Laravel déchiffre avec les anciennes clés) et planifier la ré-inscription 2FA ; les codes de secours doivent alors être régénérés | utilisateurs 2FA à reconfigurer |
| Mot de passe d'un utilisateur | `/profil` (mot de passe actuel exigé) ; les autres appareils sont déconnectés | — |

Après toute rotation : `php artisan security:check`, test de connexion, test d'un envoi simulé.
