<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidStockAdjustmentException extends InvalidArgumentException
{
    public static function nonPositiveQuantity(int $quantity): self
    {
        return new self("Quantity must be a positive integer, {$quantity} given.");
    }

    public static function invalidPrice(mixed $price): self
    {
        return new self('Unit price must be a non-negative amount with at most 2 decimals, '.var_export($price, true).' given.');
    }
}
