<?php

namespace App\Domain\Credits;

use App\Models\SmsRate;
use Illuminate\Support\Collection;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

/**
 * Coût d'un message = segments × tarif du segment.
 * Tarif : préfixe d'opérateur le plus long correspondant (dans le pays du numéro) > tarif du pays > tarif par défaut.
 * Aucun préfixe ni tarif n'est supposé : tout vient de la table sms_rates saisie par l'administrateur.
 */
class CostCalculator
{
    public function __construct(private SegmentCalculator $segments) {}

    public function forMessage(string $phoneE164, string $body, ?Collection $activeRates = null): MessageCost
    {
        $a = $this->segments->analyze($body);
        [$units, $label] = $this->rateFor($phoneE164, $activeRates);

        return new MessageCost($a['encoding'], max(1, $a['segments']), $units, $label);
    }

    /** Tarifs actifs, à précharger une fois pour une estimation en masse. */
    public function loadRates(): Collection
    {
        return SmsRate::where('is_active', true)->get();
    }

    /** @return array{0:int,1:?string} */
    public function rateFor(string $phoneE164, ?Collection $activeRates = null): array
    {
        $default = (int) config('credits.default_units_per_segment');
        $country = $this->countryOf($phoneE164);
        if ($country === null) {
            return [$default, null];
        }

        $best = null;
        $rates = $activeRates ? $activeRates->where('country', $country) : SmsRate::where('country', $country)->where('is_active', true)->get();
        foreach ($rates as $rate) {
            if ($rate->prefix !== null && ! str_starts_with($phoneE164, $rate->prefix)) {
                continue;
            }
            // Un préfixe correspondant l'emporte sur le tarif pays ; le plus long l'emporte.
            if ($best === null || strlen((string) $rate->prefix) > strlen((string) $best->prefix)) {
                $best = $rate;
            }
        }

        return $best ? [$best->units_per_segment, $best->label ?: ($best->prefix ?: $country)] : [$default, null];
    }

    private function countryOf(string $phoneE164): ?string
    {
        $util = PhoneNumberUtil::getInstance();
        try {
            return $util->getRegionCodeForNumber($util->parse($phoneE164, null));
        } catch (NumberParseException) {
            return null;
        }
    }
}
