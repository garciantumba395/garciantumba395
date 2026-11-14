<?php

namespace Tests\Unit;

use App\Domain\Reports\CsvCell;
use App\Domain\Reports\ReportSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReportsUnitTest extends TestCase
{
    public static function dangerous(): array
    {
        return [
            'égal' => ['=1+1', "'=1+1"],
            'plus' => ['+24381123456', "'+24381123456"],
            'moins' => ['-2+3', "'-2+3"],
            'arobase' => ['@SUM(A1)', "'@SUM(A1)"],
            'tabulation' => ["\t=cmd", "'\t=cmd"],
            'retour chariot' => ["\r=cmd", "'\r=cmd"],
            'formule dangereuse classique' => ['=HYPERLINK("http://x","clic")', "'=HYPERLINK(\"http://x\",\"clic\")"],
        ];
    }

    #[DataProvider('dangerous')]
    public function test_formula_cells_are_neutralised(string $in, string $out): void
    {
        $this->assertSame($out, CsvCell::safe($in));
    }

    public function test_harmless_cells_are_untouched(): void
    {
        foreach (['', 'Promo =Noël', '2026-11-14 10:00:00', 'Bonjour', "'déjà", '0,83', 'a-b', ' =espace'] as $v) {
            $this->assertSame($v, CsvCell::safe($v));
        }
    }

    public function test_percent_is_null_when_denominator_is_zero(): void
    {
        $this->assertNull(ReportSummary::percent(0, 0));
        $this->assertNull(ReportSummary::percent(5, 0));
        $this->assertSame('50,0 %', ReportSummary::percent(1, 2));
        $this->assertSame('0,0 %', ReportSummary::percent(0, 10));
    }

    public function test_rates_are_not_computable_without_sent_messages(): void
    {
        $empty = new ReportSummary(3, 0, 0, 0, 0, 0, 3, ['other' => 3], 0, 0, 0);
        $this->assertNull($empty->deliveryRate());
        $this->assertNull($empty->failureRate());

        $some = new ReportSummary(4, 3, 1, 1, 0, 0, 0, [], 0, 0, 0);
        $this->assertSame('33,3 %', $some->deliveryRate());
        $this->assertSame('25,0 %', $some->failureRate());
    }

    public function test_net_consumption_is_debits_minus_refunds(): void
    {
        $s = new ReportSummary(2, 1, 0, 1, 0, 0, 0, [], 10000, 5000, 3);
        $this->assertSame(5000, $s->consumedUnits());
        $this->assertFalse($s->isEmpty());
        $this->assertTrue((new ReportSummary(0, 0, 0, 0, 0, 0, 0, [], 0, 0, 0))->isEmpty());
    }
}
