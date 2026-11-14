<?php

namespace App\Domain\Sms;

use RuntimeException;

/**
 * Fournisseur FICTIF : n'effectue AUCUN appel réseau et n'envoie rien à personne.
 * Les messages « envoyés » sont seulement gardés en mémoire (tests) et inscrits au journal avec simulated = true.
 */
class FakeSmsProvider implements SmsProvider
{
    /** @var array<int,SmsRequest> */
    private array $sent = [];

    public function name(): string
    {
        return 'fake';
    }

    public function isLive(): bool
    {
        return false;
    }

    public function send(SmsRequest $request): SmsResult
    {
        if (in_array($request->to, config('sms.fake.throw_numbers', []), true)) {
            throw new RuntimeException('Erreur de transport simulée.');
        }
        if (in_array($request->to, config('sms.fake.reject_numbers', []), true)) {
            return SmsResult::rejected('FAKE_REJECTED', 'Numéro refusé (simulation).');
        }

        $this->sent[] = $request;

        return SmsResult::accepted('fake-'.substr(hash('sha256', $request->reference), 0, 16));
    }

    /** @return array<int,SmsRequest> */
    public function sentRequests(): array
    {
        return $this->sent;
    }
}
