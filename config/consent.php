<?php

/*
 | Paramètres du consentement. AUCUNE règle juridique locale n'est supposée ici :
 | ces valeurs sont des choix techniques configurables, à faire valider (décision D4).
 */
return [
    // Sources de consentement proposées dans l'interface (clé => libellé).
    'sources' => [
        'web_form' => 'Formulaire web',
        'sms_keyword' => 'Mot-clé envoyé par SMS',
        'paper' => 'Formulaire papier',
        'recorded_call' => 'Appel enregistré',
        'other' => 'Autre (à préciser dans la preuve)',
    ],

    // Exiger un texte de preuve (formulation présentée, référence du document…) pour tout consentement.
    'require_evidence' => env('CONSENT_REQUIRE_EVIDENCE', true),

    // Durée de validité d'un consentement en jours. null = pas d'expiration (à définir avec le juriste).
    'max_age_days' => env('CONSENT_MAX_AGE_DAYS') ? (int) env('CONSENT_MAX_AGE_DAYS') : null,
];
