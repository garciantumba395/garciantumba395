<?php

namespace App\Domain\Sms;

final class SmsResult
{
    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerMessageId,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
    ) {}

    /** Le fournisseur a pris le message en charge. */
    public static function accepted(string $providerMessageId): self
    {
        return new self(true, $providerMessageId, null, null);
    }

    /** Refus DÉFINITIF (numéro invalide, solde fournisseur…) : le message n'est pas parti. */
    public static function rejected(string $code, string $message): self
    {
        return new self(false, null, $code, $message);
    }
}
