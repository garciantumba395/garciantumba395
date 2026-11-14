<?php

namespace App\Domain\Reports;

/**
 * Totaux d'UN seul bloc (réel OU simulé) sur la période. Il n'existe volontairement aucune méthode qui additionne
 * deux blocs : un ReportSummary ne décrit jamais que des messages de même nature (`simulated`).
 */
final class ReportSummary
{
    /**
     * @param  array<string,int>  $blockedByReason  motif => nombre (tous les motifs connus + « other »)
     */
    public function __construct(
        public readonly int $total,           // tous les messages du journal sur la période (tous statuts)
        public readonly int $sent,            // statuts sent + delivered
        public readonly int $delivered,       // statut delivered (accusé du fournisseur)
        public readonly int $failed,
        public readonly int $unknown,
        public readonly int $inProgress,      // pending + sending
        public readonly int $blocked,
        public readonly array $blockedByReason,
        public readonly int $debitedUnits,    // grand livre : débits
        public readonly int $refundedUnits,   // grand livre : remboursements
        public readonly int $ledgerEntries,   // nombre d'écritures débit/remboursement de la période
    ) {}

    /** Aucune donnée : ni message ni écriture de grand livre sur la période. */
    public function isEmpty(): bool
    {
        return $this->total === 0 && $this->ledgerEntries === 0;
    }

    /** Crédits consommés = débits − remboursements (grand livre), en unités. */
    public function consumedUnits(): int
    {
        return $this->debitedUnits - $this->refundedUnits;
    }

    /** Taux de messages « délivrés » parmi les « envoyés » ; null si aucun envoyé (jamais de division par zéro). */
    public function deliveryRate(): ?string
    {
        return self::percent($this->delivered, $this->sent);
    }

    /** Échecs parmi les envois tentés (envoyés + échecs + résultat inconnu) ; null si dénominateur nul. */
    public function failureRate(): ?string
    {
        return self::percent($this->failed, $this->sent + $this->failed + $this->unknown);
    }

    /** Pourcentage à 1 décimale (format français), ou null si le dénominateur est nul. */
    public static function percent(int $numerator, int $denominator): ?string
    {
        if ($denominator <= 0) {
            return null;
        }

        return number_format($numerator * 100 / $denominator, 1, ',', ' ').' %';
    }
}
