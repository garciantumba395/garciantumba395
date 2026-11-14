<?php

/*
 | Rapports d'envoi (étape 7). Valeurs TECHNIQUES par défaut, à valider (voir docs/DECISIONS.md D20 à D26).
 | Aucune valeur n'est une règle légale ou commerciale.
 */
return [
    // Période affichée par défaut : N derniers jours (aujourd'hui inclus), dans le fuseau de l'application.
    'default_days' => (int) env('REPORTS_DEFAULT_DAYS', 30),

    // Longueur maximale d'une période (jours, bornes incluses) : borne la taille des séries et des exports.
    'max_range_days' => (int) env('REPORTS_MAX_RANGE_DAYS', 366),

    // Plafond de lignes d'un export CSV. Au-delà : export REFUSÉ avec un message (jamais tronqué en silence).
    'export_max_rows' => (int) env('REPORTS_EXPORT_MAX_ROWS', 100000),

    // Séparateur CSV : « ; » par défaut (Excel en configuration régionale française/belge/congolaise) ; « , » possible.
    'csv_delimiter' => env('REPORTS_CSV_DELIMITER', ';'),

    // Taille des lots lus en base pendant l'export (mémoire constante).
    'export_chunk' => 1000,
];
