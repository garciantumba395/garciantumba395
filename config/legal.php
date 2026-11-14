<?php

/*
 | Conformité légale RDC (décision D4). Source : Ordonnance-loi n° 23/010 du 13 mars 2023 portant Code du numérique — voir docs/CADRE_LEGAL_RDC.md,
 | qui indique pour chaque règle l'article, ce qui est appliqué et ce qui reste à valider par un juriste (le texte lu est une reproduction non officielle).
 | Aucune valeur secrète ici.
 */
return [
    /*
     | Pied de message légal automatique sur toute campagne (art. 66 : publicité identifiée et expéditeur identifiable ; art. 72 : moyen gratuit
     | d'arrêter les messages ; art. 348 : lien de désabonnement obligatoire). Ne doit être désactivé qu'en développement :
     | `php artisan security:check` signale la désactivation en production comme BLOQUANTE.
     */
    'enforce' => (bool) env('LEGAL_ENFORCE', true),

    'footer' => [
        'advertising_mention' => 'Publicité',   // art. 66 : mention « publicité » claire (le « é » reste dans l'alphabet GSM-7 : pas de surcoût)
        'unsubscribe_label' => 'Désabonnement', // art. 72 / 348
    ],

    'unsubscribe' => [
        'code_length' => 10,        // codes aléatoires alphanumériques (62^10 combinaisons : non devinables), liens courts pour tenir dans un SMS
        'rate_limit_per_minute' => (int) env('LEGAL_UNSUBSCRIBE_RATE_LIMIT', 30),   // par adresse IP
    ],
];
