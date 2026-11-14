<?php

namespace Tests\Support;

use App\Domain\Sms\SmsProvider;
use App\Domain\Sms\SmsRequest;
use App\Domain\Sms\SmsResult;

/** Se fait passer pour un fournisseur RÉEL (isLive = true) sans aucun réseau : sert à tester le verrou d'activation. */
class CountingLiveProvider implements SmsProvider
{
    public int $calls = 0;

    public function name(): string
    {
        return 'live-test';
    }

    public function isLive(): bool
    {
        return true;
    }

    public function send(SmsRequest $request): SmsResult
    {
        $this->calls++;

        return SmsResult::accepted('live-'.$this->calls);
    }
}
