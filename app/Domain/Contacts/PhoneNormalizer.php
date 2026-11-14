<?php

namespace App\Domain\Contacts;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Normalise un numéro en E.164 (+243…) selon le pays de l'entreprise.
 * Les règles de numérotation viennent de libphonenumber : aucune règle locale n'est codée en dur ici.
 */
class PhoneNormalizer
{
    /** @return string|null E.164 ou null si invalide */
    public function normalize(?string $raw, string $defaultCountry = 'CD'): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || strlen($raw) > 40) {
            return null;
        }
        // Les tableurs transforment souvent +243… en 2,43E+11 : on refuse plutôt que de deviner.
        if (preg_match('/^\d(\.|,)\d+e\+?\d+$/i', $raw)) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();
        try {
            $number = $util->parse($raw, strtoupper($defaultCountry));
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($number)) {
            return null;
        }

        // Un SMS marketing doit viser un mobile ; les fixes/numéros spéciaux sont refusés.
        $type = $util->getNumberType($number);
        if (! in_array($type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    /** Liste [code pays => "Nom (+indicatif)"] pour les sélecteurs. */
    public static function countries(): array
    {
        $util = PhoneNumberUtil::getInstance();
        $out = [];
        foreach ($util->getSupportedRegions() as $region) {
            if (strlen($region) !== 2 || $region === '001') {
                continue;
            }
            $name = \Locale::getDisplayRegion('-'.$region, 'fr') ?: $region;
            $out[$region] = sprintf('%s (+%d)', $name, $util->getCountryCodeForRegion($region));
        }
        asort($out, SORT_LOCALE_STRING);

        return $out;
    }

    public static function isSupportedCountry(string $code): bool
    {
        return in_array(strtoupper($code), PhoneNumberUtil::getInstance()->getSupportedRegions(), true);
    }
}
