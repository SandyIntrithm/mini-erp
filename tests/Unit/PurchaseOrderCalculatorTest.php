<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStockAdjustmentException;
use App\Services\PurchaseOrderCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PurchaseOrderCalculatorTest extends TestCase
{
    private PurchaseOrderCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PurchaseOrderCalculator;
    }

    public static function lineTotals(): array
    {
        return [
            'whole price' => [3, '10', '30.00'],
            'two decimals' => [7, '19.99', '139.93'],
            'one decimal' => [4, '2.5', '10.00'],
            'float input' => [3, 0.1, '0.30'],
            'zero price (free sample)' => [5, '0.00', '0.00'],
            'single unit' => [1, '1234.56', '1234.56'],
            'large order' => [100000, '999999.99', '99999999000.00'],
        ];
    }

    #[Test]
    #[DataProvider('lineTotals')]
    public function it_calculates_line_item_subtotals_exactly(int $quantity, string|float $price, string $expected): void
    {
        $this->assertSame($expected, $this->calculator->lineTotal($quantity, $price));
    }

    #[Test]
    public function it_calculates_the_grand_total_across_lines(): void
    {
        $total = $this->calculator->grandTotal([
            ['quantity' => 10, 'unit_price' => '2.50'],
            ['quantity' => 3, 'unit_price' => '9.99'],
            ['quantity' => 1, 'unit_price' => '0.03'],
        ]);

        $this->assertSame('55.00', $total);
    }

    #[Test]
    public function it_avoids_floating_point_drift(): void
    {
        $total = $this->calculator->grandTotal([
            ['quantity' => 1, 'unit_price' => 0.1],
            ['quantity' => 1, 'unit_price' => 0.2],
        ]);

        $this->assertSame('0.30', $total);
    }

    #[Test]
    public function grand_total_of_no_lines_is_zero(): void
    {
        $this->assertSame('0.00', $this->calculator->grandTotal([]));
    }

    #[Test]
    public function it_builds_normalised_lines_and_ignores_extra_client_fields(): void
    {
        $result = $this->calculator->calculate([
            ['product_id' => '5', 'quantity' => '2', 'unit_price' => '10.5', 'line_total' => '999999'],
            ['product_id' => 9, 'quantity' => 4, 'unit_price' => '0.25'],
        ]);

        $this->assertSame([
            ['product_id' => 5, 'quantity' => 2, 'unit_price' => '10.50', 'line_total' => '21.00'],
            ['product_id' => 9, 'quantity' => 4, 'unit_price' => '0.25', 'line_total' => '1.00'],
        ], $result['lines']);
        $this->assertSame('22.00', $result['total']);
    }

    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'large negative' => [-500],
        ];
    }

    #[Test]
    #[DataProvider('invalidQuantities')]
    public function it_rejects_zero_or_negative_quantities(int $quantity): void
    {
        $this->expectException(InvalidStockAdjustmentException::class);

        $this->calculator->lineTotal($quantity, '10.00');
    }

    #[Test]
    public function it_rejects_a_negative_quantity_anywhere_in_the_order(): void
    {
        $this->expectException(InvalidStockAdjustmentException::class);

        $this->calculator->grandTotal([
            ['quantity' => 5, 'unit_price' => '1.00'],
            ['quantity' => -2, 'unit_price' => '1.00'],
        ]);
    }

    public static function invalidPrices(): array
    {
        return [
            'negative' => ['-1.00'],
            'three decimals' => ['1.005'],
            'non numeric' => ['abc'],
            'empty' => [''],
            'exponent' => ['1e3'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPrices')]
    public function it_rejects_invalid_unit_prices(string $price): void
    {
        $this->expectException(InvalidStockAdjustmentException::class);

        $this->calculator->lineTotal(1, $price);
    }

    #[Test]
    public function it_converts_between_amounts_and_cents(): void
    {
        $this->assertSame(1999, $this->calculator->toCents('19.99'));
        $this->assertSame(1990, $this->calculator->toCents('19.9'));
        $this->assertSame(1900, $this->calculator->toCents(19));
        $this->assertSame('19.99', $this->calculator->fromCents(1999));
        $this->assertSame('0.05', $this->calculator->fromCents(5));
    }
}
