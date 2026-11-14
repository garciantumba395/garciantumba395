<?php

namespace Tests\Feature\Sms;

use App\Domain\Sms\FakeSmsProvider;
use App\Domain\Sms\SmsProvider;
use App\Domain\Sms\SmsRequest;
use Tests\Contracts\SmsProviderContractTest;

/** Le fournisseur fictif doit passer le contrat que tout adaptateur réel devra aussi passer. */
class FakeSmsProviderContractTest extends SmsProviderContractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.fake.reject_numbers' => ['+243810000404'], 'sms.fake.throw_numbers' => ['+243810000500']]);
    }

    protected function makeProvider(): SmsProvider
    {
        return new FakeSmsProvider;
    }

    protected function rejectedRequest(): ?SmsRequest
    {
        return new SmsRequest('+243810000404', 'Refusé (test)', 'contract-ref-rejected');
    }

    protected function transportErrorRequest(): ?SmsRequest
    {
        return new SmsRequest('+243810000500', 'Panne (test)', 'contract-ref-transport');
    }

    protected function referenceIsIdempotent(): bool
    {
        return true;
    }

    public function test_the_fake_provider_is_never_live(): void
    {
        $this->assertFalse($this->makeProvider()->isLive());
        $this->assertSame('fake', $this->makeProvider()->name());
    }
}
