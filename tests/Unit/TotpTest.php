<?php

namespace Tests\Unit;

use App\Support\Security\Totp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Vecteurs de test officiels : RFC 4226 (annexe D) et RFC 6238 (annexe B). */
class TotpTest extends TestCase
{
    private const SECRET_SHA1 = '12345678901234567890';

    private const SECRET_SHA256 = '12345678901234567890123456789012';

    private const SECRET_SHA512 = '1234567890123456789012345678901234567890123456789012345678901234';

    public function test_rfc4226_hotp_vectors(): void
    {
        $secret = Totp::base32Encode(self::SECRET_SHA1);
        $expected = ['755224', '287082', '359152', '969429', '338314', '254676', '287922', '162583', '399871', '520489'];
        foreach ($expected as $counter => $code) {
            $this->assertSame($code, Totp::hotp($secret, $counter), "compteur $counter");
        }
    }

    public static function rfc6238Vectors(): array
    {
        return [
            // [horodatage, SHA1, SHA256, SHA512] (8 chiffres)
            [59, '94287082', '46119246', '90693936'],
            [1111111109, '07081804', '68084774', '25091201'],
            [1111111111, '14050471', '67062674', '99943326'],
            [1234567890, '89005924', '91819424', '93441116'],
            [2000000000, '69279037', '90698825', '38618901'],
            [20000000000, '65353130', '77737706', '47863826'],
        ];
    }

    #[DataProvider('rfc6238Vectors')]
    public function test_rfc6238_vectors(int $time, string $sha1, string $sha256, string $sha512): void
    {
        $this->assertSame($sha1, Totp::at(Totp::base32Encode(self::SECRET_SHA1), $time, 8));
        $this->assertSame($sha256, Totp::at(Totp::base32Encode(self::SECRET_SHA256), $time, 8, 30, 'sha256'));
        $this->assertSame($sha512, Totp::at(Totp::base32Encode(self::SECRET_SHA512), $time, 8, 30, 'sha512'));
    }

    public function test_base32_roundtrip_and_rfc4648_vectors(): void
    {
        $this->assertSame('MZXW6YTBOI', Totp::base32Encode('foobar'));
        $this->assertSame('foobar', Totp::base32Decode('MZXW6YTBOI======'));
        $this->assertSame('foobar', Totp::base32Decode('mzxw 6ytb-oi'));
        $secret = Totp::generateSecret();
        $this->assertSame(32, strlen($secret));
        $this->assertSame($secret, Totp::base32Encode(Totp::base32Decode($secret)));
        $this->assertNotSame($secret, Totp::generateSecret());
    }

    public function test_invalid_base32_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Totp::base32Decode('abc1');
    }

    public function test_find_step_respects_window_and_format(): void
    {
        $secret = Totp::base32Encode(self::SECRET_SHA1);
        $now = 1111111109;
        $current = Totp::step($now);
        $code = fn (int $offset) => Totp::hotp($secret, $current + $offset);

        $this->assertSame($current, Totp::findStep($secret, $code(0), $now, 1));
        $this->assertSame($current - 1, Totp::findStep($secret, $code(-1), $now, 1));
        $this->assertSame($current + 1, Totp::findStep($secret, $code(1), $now, 1));
        $this->assertNull(Totp::findStep($secret, $code(-2), $now, 1));
        $this->assertNull(Totp::findStep($secret, $code(2), $now, 1));
        $this->assertNull(Totp::findStep($secret, $code(-1), $now, 0));
        $this->assertNull(Totp::findStep($secret, '12345', $now, 1));
        $this->assertNull(Totp::findStep($secret, 'abcdef', $now, 1));
        $this->assertNull(Totp::findStep($secret, '', $now, 1));
    }

    public function test_otpauth_uri(): void
    {
        $uri = Totp::uri('SMS Marketing', 'a@example.com', 'JBSWY3DPEHPK3PXP');
        $this->assertStringStartsWith('otpauth://totp/SMS%20Marketing%3Aa%40example.com?', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=SMS%20Marketing', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
