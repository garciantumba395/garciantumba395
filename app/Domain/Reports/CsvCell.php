<?php

namespace App\Domain\Reports;

/**
 * Protection contre l'injection de formules (CSV injection) : un tableur interprète comme une formule toute cellule
 * commençant par « = », « + », « - », « @ », tabulation ou retour chariot. On la préfixe d'une apostrophe, qui
 * la rend inerte (le texte reste lisible). S'applique à TOUTE cellule texte exportée, y compris les numéros.
 */
final class CsvCell
{
    private const DANGEROUS_FIRST_CHARS = ['=', '+', '-', '@', "\t", "\r"];

    public static function safe(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::DANGEROUS_FIRST_CHARS, true)) {
            return "'".$value;
        }

        return $value;
    }
}
