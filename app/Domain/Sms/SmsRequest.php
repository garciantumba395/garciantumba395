<?php

namespace App\Domain\Sms;

final class SmsRequest
{
    public function __construct(
        public readonly string $to,          // E.164
        public readonly string $body,
        public readonly string $reference,   // référence unique côté plateforme (idempotence côté fournisseur si supportée)
        public readonly ?string $senderId = null,
    ) {}
}
