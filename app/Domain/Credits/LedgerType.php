<?php

namespace App\Domain\Credits;

enum LedgerType: string
{
    case Credit = 'credit';          // ajout par l'administrateur
    case Debit = 'debit';            // envoi d'un message
    case Refund = 'refund';          // annulation d'un débit (refus définitif du fournisseur)
    case Adjustment = 'adjustment';  // correction par l'administrateur (+/−)

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Ajout de crédits',
            self::Debit => 'Envoi',
            self::Refund => 'Remboursement',
            self::Adjustment => 'Correction',
        };
    }
}
