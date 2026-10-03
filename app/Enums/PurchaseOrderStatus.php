<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'DRAFT';
    case Approved = 'APPROVED';
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Cancelled],
            self::Approved => [self::Received, self::Cancelled],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this === self::Received || $this === self::Cancelled;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Approved => 'text-bg-primary',
            self::Received => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Draft => 'Revert to Draft',
            self::Approved => 'Approve',
            self::Received => 'Mark as Received',
            self::Cancelled => 'Cancel Order',
        };
    }

    public function actionButtonClass(): string
    {
        return match ($this) {
            self::Approved => 'btn-primary',
            self::Received => 'btn-success',
            self::Cancelled => 'btn-outline-danger',
            default => 'btn-secondary',
        };
    }

    public static function targetValues(): array
    {
        return [self::Approved->value, self::Received->value, self::Cancelled->value];
    }
}
