<?php

namespace Tests\Unit;

use App\Domain\Contacts\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('valid')]
    public function test_valid_numbers_become_e164(string $raw, string $expected): void
    {
        $this->assertSame($expected, (new PhoneNormalizer)->normalize($raw, 'CD'));
    }

    public static function valid(): array
    {
        return [
            'local avec 0' => ['0812345678', '+243812345678'],
            'international espaces' => ['+243 81 234 5678', '+243812345678'],
            'sans plus' => ['243812345678', '+243812345678'],
            'autre pays accepté' => ['+33612345678', '+33612345678'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_invalid_numbers_are_rejected(?string $raw): void
    {
        $this->assertNull((new PhoneNormalizer)->normalize($raw, 'CD'));
    }

    public static function invalid(): array
    {
        return [[''], [null], ['abc'], ['12345'], ['+243 20 123 456'], ['2,43E+11'], [str_repeat('1', 60)]];
    }

    public function test_country_changes_interpretation_of_local_numbers(): void
    {
        $this->assertSame('+33612345678', (new PhoneNormalizer)->normalize('06 12 34 56 78', 'FR'));
        $this->assertNull((new PhoneNormalizer)->normalize('06 12 34 56 78', 'CD'));
    }

    public function test_supported_country_check_and_list(): void
    {
        $this->assertTrue(PhoneNormalizer::isSupportedCountry('cd'));
        $this->assertFalse(PhoneNormalizer::isSupportedCountry('ZZ'));
        $this->assertStringContainsString('+243', PhoneNormalizer::countries()['CD']);
    }
}
