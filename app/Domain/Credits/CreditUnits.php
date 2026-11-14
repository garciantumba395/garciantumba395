<?php

namespace App\Domain\Credits;

use InvalidArgumentException;

/** Conversions crédits ⇄ unités entières (aucun calcul en virgule flottante). */
final class CreditUnits
{
    /** "12.5" ou "12,5" (2 décimales max) → unités. */
    public static function fromCredits(string $credits): int
    {
        $credits = str_replace(',', '.', trim($credits));
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $credits, $m)) {
            throw new InvalidArgumentException('Montant invalide (nombre positif, 2 décimales maximum).');
        }
        $perCredit = (int) config('credits.units_per_credit');
        $hundredth = intdiv($perCredit, 100);   // unités par centième de crédit

        return (int) $m[1] * $perCredit + (int) str_pad($m[2] ?? '0', 2, '0') * $hundredth;
    }

    /** Unités → texte en crédits, 2 décimales (affichage uniquement). */
    public static function format(int $units): string
    {
        $perCredit = (int) config('credits.units_per_credit');
        $sign = $units < 0 ? '−' : '';
        $abs = abs($units);
        $hundredths = intdiv($abs * 100 + intdiv($perCredit, 2), $perCredit); // arrondi au centième

        return $sign.number_format(intdiv($hundredths, 100), 0, ',', ' ').','.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Nombre de segments couverts par 100 crédits à un tarif donné (affichage : « SMS pour 100 crédits »). */
    public static function smsPer100Credits(int $unitsPerSegment): float
    {
        return round(100 * (int) config('credits.units_per_credit') / max(1, $unitsPerSegment), 2);
    }

    /** « SMS pour 100 crédits » → unités par segment (arrondi à l'unité). */
    public static function unitsPerSegmentFromSmsPer100(int $sms): int
    {
        if ($sms < 1 || $sms > 100000) {
            throw new InvalidArgumentException('Valeur invalide.');
        }

        return max(1, (int) round(100 * (int) config('credits.units_per_credit') / $sms));
    }
}
