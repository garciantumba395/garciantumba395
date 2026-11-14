<?php

namespace App\Domain\Credits;

/**
 * Nombre de segments d'un SMS selon la norme (3GPP TS 23.038) :
 *  - GSM-7 : 160 caractères (1 segment) puis 153 par segment ; les caractères de l'extension (^ { } \ [ ~ ] | €) comptent double ;
 *  - UCS-2 : 70 caractères (1 segment) puis 67 par segment (unités UTF-16, un emoji compte 2).
 * Un seul caractère hors GSM-7 (ex. « ç », « â », « ê », emoji) fait passer TOUT le message en UCS-2.
 * Simplification volontaire : pas de tables de « national language shift » (le calcul peut sur-estimer, jamais sous-estimer).
 */
class SegmentCalculator
{
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENDED = '^{}\\[~]|€';

    /** @return array{encoding:string, length:int, segments:int} */
    public function analyze(string $body): array
    {
        $chars = mb_str_split($body);
        $gsmLength = 0;
        $isGsm = true;
        foreach ($chars as $c) {
            if (mb_strpos(self::GSM_BASIC, $c) !== false) {
                $gsmLength++;
            } elseif (mb_strpos(self::GSM_EXTENDED, $c) !== false) {
                $gsmLength += 2;
            } else {
                $isGsm = false;
                break;
            }
        }

        if ($isGsm) {
            return ['encoding' => 'gsm7', 'length' => $gsmLength, 'segments' => $this->count($gsmLength, 160, 153)];
        }

        $units = intdiv(strlen((string) mb_convert_encoding($body, 'UTF-16BE', 'UTF-8')), 2);

        return ['encoding' => 'ucs2', 'length' => $units, 'segments' => $this->count($units, 70, 67)];
    }

    private function count(int $length, int $single, int $multi): int
    {
        if ($length === 0) {
            return 0;
        }

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
