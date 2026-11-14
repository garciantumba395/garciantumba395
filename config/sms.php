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

    // Comportement du fournisseur fictif (tests et démonstration).
    'fake' => [
        'reject_numbers' => [],   // numéros E.164 refusés définitivement
        'throw_numbers' => [],    // numéros provoquant une erreur de transport (résultat inconnu)
    ],
];
