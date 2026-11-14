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

    // Comportement du fournisseur fictif (tests et démonstration).
    'fake' => [
        'reject_numbers' => [],   // numéros E.164 refusés définitivement
        'throw_numbers' => [],    // numéros provoquant une erreur de transport (résultat inconnu)
    ],
];
