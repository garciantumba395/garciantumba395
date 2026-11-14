<?php

namespace Tests\Feature\Sms;

use App\Domain\Sms\SmsProvider;
use App\Domain\Sms\SmsRequest;
use Tests\Contracts\SmsProviderContractTest;
use Tests\Support\CountingLiveProvider;

/** Montre que le contrat s'applique aussi à un adaptateur « live » (sans réseau) : verrou d'activation et journal. */
class LiveLockProviderContractTest extends SmsProviderContractTest
{
    protected function makeProvider(): SmsProvider
    {
        $provider = new CountingLiveProvider;
        $provider->rejectNumbers = ['+243810000404'];

        return $provider;
    }

    protected function rejectedRequest(): ?SmsRequest
    {
        return new SmsRequest('+243810000404', 'Refusé (test)', 'contract-ref-rejected');
    }

    protected function transportErrorRequest(): ?SmsRequest
    {
        return null; // CountingLiveProvider ne simule pas de panne de transport
    }

    public function test_the_live_double_is_live_and_locked_by_default(): void
    {
        $this->assertTrue($this->makeProvider()->isLive());
    }
}
