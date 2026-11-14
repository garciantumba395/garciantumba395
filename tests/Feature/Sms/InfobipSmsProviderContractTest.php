<?php

namespace Tests\Feature\Sms;

use App\Domain\Sms\InfobipSmsProvider;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsProvider;
use App\Domain\Sms\SmsRequest;
use App\Domain\Sms\SmsSendingDisabledException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Contracts\SmsProviderContractTest;

/** Adaptateur Infobip : contrat commun, entièrement simulé (Http::fake), aucune clé réelle, aucun appel réseau. */
class InfobipSmsProviderContractTest extends SmsProviderContractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::swap(new Factory); // écarte le Http::fake() vide du contrat parent (premier gagnant)
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $msg = $request->data()['messages'][0];
            $to = $msg['destinations'][0]['to'];
            if ($to === '243810000404') {
                return Http::response(['requestError' => ['serviceException' => ['text' => 'Bad request']]], 400);
            }
            if ($to === '243810000500') {
                throw new ConnectionException('timeout');
            }
            if ($to === '243810000502') {
                return Http::response('', 502);
            }
            if ($to === '243810000403') {
                return Http::response('', 401);
            }
            if ($to === '243810000501') {
                return Http::response(['messages' => [['status' => ['groupName' => 'PENDING']]]], 200); // sans identifiant
            }
            if ($to === '243810000601') {
                return Http::response(['messages' => [['messageId' => 'x1', 'status' => ['groupName' => 'REJECTED']]]], 200);
            }

            return Http::response(['messages' => [['messageId' => $msg['messageId'], 'status' => ['groupName' => 'PENDING']]]], 200);
        });
    }

    protected function makeProvider(): SmsProvider
    {
        return new InfobipSmsProvider('test.example.invalid', 'cle-fictive-de-test', 'Demo');
    }

    protected function rejectedRequest(): ?SmsRequest
    {
        return new SmsRequest('+243810000404', 'Refusé (test)', 'm-rej');
    }

    protected function transportErrorRequest(): ?SmsRequest
    {
        return new SmsRequest('+243810000500', 'Panne (test)', 'm-err');
    }

    public function test_request_format_auth_and_reference(): void
    {
        $this->makeProvider()->send(new SmsRequest('+243812345678', 'Texte', 'm42', 'MaBoutique'));

        Http::assertSent(function (Request $r) {
            $m = $r->data()['messages'][0];

            return $r->url() === 'https://test.example.invalid/sms/3/messages'
                && $r->header('Authorization') === ['App cle-fictive-de-test']
                && $m['messageId'] === 'm42' && $m['sender'] === 'MaBoutique'
                && $m['destinations'][0]['to'] === '243812345678' && $m['content']['text'] === 'Texte';
        });
    }

    public function test_ambiguous_answers_are_unknown_never_a_result(): void
    {
        foreach (['+243810000502', '+243810000403', '+243810000501'] as $to) {
            try {
                $this->makeProvider()->send(new SmsRequest($to, 'x', 'm-amb'));
                $this->fail("$to aurait dû lever une exception");
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_provider_side_rejection_status_is_definitive(): void
    {
        $r = $this->makeProvider()->send(new SmsRequest('+243810000601', 'x', 'm-r'));
        $this->assertFalse($r->accepted);
        $this->assertSame('infobip_rejected', $r->errorCode);
    }

    public function test_unconfigured_provider_never_calls_the_network(): void
    {
        $this->expectException(\RuntimeException::class);
        try {
            (new InfobipSmsProvider('', ''))->send(new SmsRequest('+243812345678', 'x', 'm1'));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_activation_lock_applies_and_secrets_stay_out_of_defaults(): void
    {
        config(['sms.provider' => 'infobip', 'sms.sending_enabled' => false]);
        $this->expectException(SmsSendingDisabledException::class);
        try {
            app(SmsManager::class)->provider();
        } finally {
            $this->assertSame('', config('sms.infobip.api_key'));
        }
    }
}
