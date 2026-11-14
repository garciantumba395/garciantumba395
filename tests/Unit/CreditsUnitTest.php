<?php

namespace Tests\Unit;

use App\Domain\Credits\CreditUnits;
use App\Domain\Credits\SegmentCalculator;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreditsUnitTest extends TestCase
{
    public function test_hundred_credits_equal_exactly_120_default_segments(): void
    {
        $units = CreditUnits::fromCredits('100');
        $this->assertSame(600000, $units);
        $this->assertSame(120, intdiv($units, config('credits.default_units_per_segment')));
        $this->assertSame(0, $units % config('credits.default_units_per_segment')); // exact, sans reste
        $this->assertSame(120.0, CreditUnits::smsPer100Credits(config('credits.default_units_per_segment')));
    }

    #[DataProvider('amounts')]
    public function test_credit_parsing(string $in, int $units): void
    {
        $this->assertSame($units, CreditUnits::fromCredits($in));
    }

    public static function amounts(): array
    {
        return [['1', 6000], ['0.01', 60], ['12,5', 75000], ['0.83', 4980], ['  7.05 ', 42300]];
    }

    #[DataProvider('badAmounts')]
    public function test_invalid_amounts_are_rejected(string $in): void
    {
        $this->expectException(InvalidArgumentException::class);
        CreditUnits::fromCredits($in);
    }

    public static function badAmounts(): array
    {
        return [[''], ['abc'], ['-5'], ['1.234'], ['1e3'], ['1.'], ['.5']];
    }

    public function test_formatting(): void
    {
        $this->assertSame('100,00', CreditUnits::format(600000));
        $this->assertSame('0,83', CreditUnits::format(5000));
        $this->assertSame('−0,83', CreditUnits::format(-5000));
        $this->assertSame('1 234,50', CreditUnits::format(1234 * 6000 + 3000));
    }

    public function test_rate_conversion(): void
    {
        $this->assertSame(5000, CreditUnits::unitsPerSegmentFromSmsPer100(120));
        $this->assertSame(6000, CreditUnits::unitsPerSegmentFromSmsPer100(100));
        $this->assertSame(4000, CreditUnits::unitsPerSegmentFromSmsPer100(150));
        $this->expectException(InvalidArgumentException::class);
        CreditUnits::unitsPerSegmentFromSmsPer100(0);
    }

    #[DataProvider('segments')]
    public function test_segment_counting(string $body, string $encoding, int $segments): void
    {
        $r = (new SegmentCalculator)->analyze($body);
        $this->assertSame($encoding, $r['encoding']);
        $this->assertSame($segments, $r['segments']);
    }

    public static function segments(): array
    {
        return [
            'vide' => ['', 'gsm7', 0],
            'court' => ['Bonjour', 'gsm7', 1],
            '160 gsm' => [str_repeat('a', 160), 'gsm7', 1],
            '161 gsm' => [str_repeat('a', 161), 'gsm7', 2],
            '306 gsm' => [str_repeat('a', 306), 'gsm7', 2],
            '307 gsm' => [str_repeat('a', 307), 'gsm7', 3],
            'accents gsm' => [str_repeat('é', 160), 'gsm7', 1],
            'extension double' => [str_repeat('€', 80), 'gsm7', 1],
            'extension 81' => [str_repeat('€', 81), 'gsm7', 2],
            'ç force ucs2' => ['Bonjour ça va', 'ucs2', 1],
            '70 ucs2' => [str_repeat('ç', 70), 'ucs2', 1],
            '71 ucs2' => [str_repeat('ç', 71), 'ucs2', 2],
            '134 ucs2' => [str_repeat('ç', 134), 'ucs2', 2],
            '135 ucs2' => [str_repeat('ç', 135), 'ucs2', 3],
            'emoji = 2 unités' => ['Salut 😀', 'ucs2', 1],
            'emoji 35' => [str_repeat('😀', 35), 'ucs2', 1],
            'emoji 36' => [str_repeat('😀', 36), 'ucs2', 2],
        ];
    }

    public function test_config_can_change_conversion(): void
    {
        Config::set('credits.units_per_credit', 1000);
        $this->assertSame(1500, CreditUnits::fromCredits('1.5'));
    }
}
