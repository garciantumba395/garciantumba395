<?php

/*
 | Envoi de SMS. PAR DÉFAUT : fournisseur fictif, aucun envoi réel.
 |
 | Un envoi réel exige DEUX actions explicites et indépendantes :
 |   1. SMS_PROVIDER=<fournisseur réel enregistré dans SmsManager>
 |   2. SMS_SENDING_ENABLED=true
 | Sans la seconde, un fournisseur « live » ne peut même pas être instancié.
 | Aucune clé d'API ne doit figurer dans ce fichier ni dans le dépôt : uniquement dans le .env du serveur.
 */
return [
    'provider' => env('SMS_PROVIDER', 'fake'),

    'sending_enabled' => (bool) env('SMS_SENDING_ENABLED', false),

    // Limite technique de sécurité sur la longueur d'un message (pas une règle de tarification).
    'max_body_length' => 1600,

    // File d'attente Redis dédiée aux envois (worker : php artisan queue:work --queue=sms).
    'queue' => env('SMS_QUEUE', 'sms'),

    /*
     | Garde-fous anti-abus de l'envoi RÉEL (décision D12 : pas d'approbation manuelle, validation automatique après les envois).
     | Valeurs techniques par défaut, volontairement prudentes, À AJUSTER (volumes D11, fournisseur retenu). Ne s'appliquent
     | qu'aux envois réels : la simulation n'est jamais limitée et ne valide jamais une entreprise.
     */
    'sending' => [
        'probation_daily_limit' => (int) env('SMS_PROBATION_DAILY_LIMIT', 100),
        'validated_daily_limit' => env('SMS_VALIDATED_DAILY_LIMIT') !== null ? (int) env('SMS_VALIDATED_DAILY_LIMIT') : null, // null = pas de plafond
        'min_sample' => 50,            // envois réels minimum avant évaluation
        'max_failure_rate' => 0.20,    // au-delà : suspension automatique
        'max_optout_rate' => 0.05,     // désinscriptions / envois réels : au-delà, suspension automatique
    ],

    // Infobip (D1). Secrets UNIQUEMENT dans le .env du serveur. Rien ne part sans SMS_PROVIDER=infobip ET SMS_SENDING_ENABLED=true.
    'infobip' => [
        'base_url' => env('INFOBIP_BASE_URL', ''),   // hôte de votre compte (sans https://)
        'api_key' => env('INFOBIP_API_KEY', ''),
        'default_sender' => env('INFOBIP_DEFAULT_SENDER'),
        'timeout' => (int) env('INFOBIP_TIMEOUT', 15),
    ],

    // Comportement du fournisseur fictif (tests et démonstration).
    'fake' => [
        'reject_numbers' => [],   // numéros E.164 refusés définitivement
        'throw_numbers' => [],    // numéros provoquant une erreur de transport (résultat inconnu)
    ],

    // --- Étape 10 (préparatoire) : webhooks, messages entrants, réconciliation. Valeurs techniques, à valider (docs/DECISIONS.md D50-D53). ---
    'webhooks' => [
        // Secret du fournisseur FICTIF. VIDE PAR DÉFAUT = tout webhook est refusé (fail-closed). Jamais de secret dans le dépôt.
        'fake_secret' => env('SMS_WEBHOOK_FAKE_SECRET', ''),
        'tolerance_seconds' => (int) env('SMS_WEBHOOK_TOLERANCE_SECONDS', 300),   // fenêtre d'horodatage (passé et futur)
        'max_body_bytes' => (int) env('SMS_WEBHOOK_MAX_BODY_BYTES', 65536),
        'rate_limit_per_minute' => (int) env('SMS_WEBHOOK_RATE_LIMIT_PER_MINUTE', 600), // par IP et fournisseur
    ],

    'inbound' => [
        // Mots-clés de désinscription (texte entier, casse/accents/espaces ignorés). Liste à valider : D4 / D14.
        'optout_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env('SMS_INBOUND_OPTOUT_KEYWORDS', 'STOP'))), fn ($k) => $k !== '')),
        // Une désinscription vise les entreprises ayant envoyé un message RÉEL à ce numéro dans cette fenêtre (jours).
        'window_days' => (int) env('SMS_INBOUND_WINDOW_DAYS', 30),
    ],

    'reconcile' => [
        // Un message « en cours » ou « résultat inconnu » depuis plus de N minutes est signalé par sms:reconcile.
        'stale_minutes' => (int) env('SMS_RECONCILE_STALE_MINUTES', 60),
    ],
];
