<?php

namespace Tests\Unit;

use App\Domain\Sms\Webhooks\HmacSignature;
use App\Domain\Sms\Webhooks\InboundKeyword;
use PHPUnit\Framework\TestCase;

class WebhookPrimitivesTest extends TestCase
{
    public function test_hmac_matches_only_the_exact_signature(): void
    {
        $sig = HmacSignature::sign('s3cret', 'payload');

        $this->assertTrue(HmacSignature::matches('s3cret', 'payload', $sig));
        $this->assertTrue(HmacSignature::matches('s3cret', 'payload', strtoupper($sig)));
        $this->assertFalse(HmacSignature::matches('s3cret', 'payload2', $sig));
        $this->assertFalse(HmacSignature::matches('autre', 'payload', $sig));
        $this->assertFalse(HmacSignature::matches('s3cret', 'payload', null));
        $this->assertFalse(HmacSignature::matches('s3cret', 'payload', ''));
        $this->assertFalse(HmacSignature::matches('s3cret', 'payload', substr($sig, 0, -1)));
    }

    public function test_hmac_with_empty_secret_never_matches(): void
    {
        $this->assertFalse(HmacSignature::matches('', 'payload', HmacSignature::sign('', 'payload')));
    }

    public function test_timestamp_tolerance_is_symmetric_and_strict_on_format(): void
    {
        $now = 1_000_000;

        $this->assertTrue(HmacSignature::timestampWithinTolerance('1000000', 300, $now));
        $this->assertTrue(HmacSignature::timestampWithinTolerance('999700', 300, $now));
        $this->assertTrue(HmacSignature::timestampWithinTolerance('1000300', 300, $now));
        $this->assertFalse(HmacSignature::timestampWithinTolerance('999699', 300, $now));
        $this->assertFalse(HmacSignature::timestampWithinTolerance('1000301', 300, $now));
        foreach ([null, '', 'abc', '-5', '1e6', '1000000.5', ' 1000000', '0x10'] as $bad) {
            $this->assertFalse(HmacSignature::timestampWithinTolerance($bad, 300, $now), (string) $bad);
        }
    }

    public function test_keyword_normalisation(): void
    {
        $this->assertSame('STOP', InboundKeyword::normalize("  \tstôp.\n"));
        $this->assertSame('ARRET SMS', InboundKeyword::normalize('arrêt    sms !'));
        $this->assertSame('', InboundKeyword::normalize(' ... '));
        $this->assertTrue(InboundKeyword::matches('Stop', ['STOP']));
        $this->assertFalse(InboundKeyword::matches('stop it', ['STOP']));
        $this->assertFalse(InboundKeyword::matches('', ['']));
        $this->assertFalse(InboundKeyword::matches('stop', []));
    }
}
