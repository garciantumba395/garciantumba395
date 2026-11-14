<?php

/*
 | Crédits prépayés. Règle fournie par le propriétaire du produit (D2) : 100 crédits = 120 SMS (1 SMS = 1 segment).
 | Les montants sont stockés en UNITÉS entières (jamais de décimales) :
 |   1 crédit = 6 000 unités ; 1 segment au tarif par défaut = 5 000 unités (= 5/6 de crédit, exact).
 | Aucun prix en argent n'est défini ici (D7). Les tarifs par pays/opérateur se saisissent dans l'administration.
 */
return [
    'units_per_credit' => 6000,

    // 100 crédits (600 000 unités) = 120 segments  →  5 000 unités par segment.
    'default_units_per_segment' => 5000,

    // Garde-fou technique sur une opération d'ajout/retrait (en crédits).
    'max_adjustment_credits' => 1000000,
];
