<?php

namespace App\Domain\Sms\Webhooks;

use Illuminate\Support\Str;

/** Reconnaissance d'un mot-clé de désinscription : casse, accents, espaces et ponctuation de bord ignorés ; texte ENTIER égal au mot-clé. */
final class InboundKeyword
{
    public static function normalize(string $text): string
    {
        $text = Str::upper(Str::ascii($text));
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim((string) preg_replace('/^[\s\p{P}]+|[\s\p{P}]+$/u', '', $text));
    }

    /** @param array<int,string> $keywords */
    public static function matches(string $text, array $keywords): bool
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return false;
        }
        foreach ($keywords as $keyword) {
            if ($normalized === self::normalize((string) $keyword)) {
                return true;
            }
        }

        return false;
    }
}
