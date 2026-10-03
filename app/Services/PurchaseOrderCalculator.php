<?php

namespace App\Services;

use App\Exceptions\InvalidStockAdjustmentException;

final class PurchaseOrderCalculator
{
    public function toCents(int|float|string $amount): int
    {
        $normalized = is_string($amount) ? trim($amount) : (string) $amount;

        if (! preg_match('/^\d+(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw InvalidStockAdjustmentException::invalidPrice($amount);
        }

        [$whole] = explode('.', $normalized);
        $fraction = str_pad($matches[1] ?? '', 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    public function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public function lineTotalCents(int $quantity, int|float|string $unitPrice): int
    {
        InventoryService::assertValidIncrement($quantity);

        return $quantity * $this->toCents($unitPrice);
    }

    public function lineTotal(int $quantity, int|float|string $unitPrice): string
    {
        return $this->fromCents($this->lineTotalCents($quantity, $unitPrice));
    }

    public function grandTotal(iterable $lines): string
    {
        $total = 0;

        foreach ($lines as $line) {
            $total += $this->lineTotalCents((int) $line['quantity'], $line['unit_price']);
        }

        return $this->fromCents($total);
    }

    public function calculate(array $items): array
    {
        $lines = [];
        $totalCents = 0;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitCents = $this->toCents($item['unit_price']);
            $lineCents = $this->lineTotalCents($quantity, $item['unit_price']);
            $totalCents += $lineCents;

            $lines[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => $this->fromCents($unitCents),
                'line_total' => $this->fromCents($lineCents),
            ];
        }

        return ['lines' => $lines, 'total' => $this->fromCents($totalCents)];
    }
}
