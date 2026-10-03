<?php

namespace Tests\Unit;

use App\Enums\PurchaseOrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PurchaseOrderStatusTest extends TestCase
{
    public static function transitions(): array
    {
        $s = fn (string $value) => PurchaseOrderStatus::from($value);

        return [
            'draft → approved' => [$s('DRAFT'), $s('APPROVED'), true],
            'draft → cancelled' => [$s('DRAFT'), $s('CANCELLED'), true],
            'draft → received (must be approved first)' => [$s('DRAFT'), $s('RECEIVED'), false],
            'draft → draft' => [$s('DRAFT'), $s('DRAFT'), false],
            'approved → received' => [$s('APPROVED'), $s('RECEIVED'), true],
            'approved → cancelled' => [$s('APPROVED'), $s('CANCELLED'), true],
            'approved → draft (backwards)' => [$s('APPROVED'), $s('DRAFT'), false],
            'approved → approved' => [$s('APPROVED'), $s('APPROVED'), false],
            'received → draft' => [$s('RECEIVED'), $s('DRAFT'), false],
            'received → approved' => [$s('RECEIVED'), $s('APPROVED'), false],
            'received → cancelled' => [$s('RECEIVED'), $s('CANCELLED'), false],
            'received → received' => [$s('RECEIVED'), $s('RECEIVED'), false],
            'cancelled → draft' => [$s('CANCELLED'), $s('DRAFT'), false],
            'cancelled → approved' => [$s('CANCELLED'), $s('APPROVED'), false],
            'cancelled → received' => [$s('CANCELLED'), $s('RECEIVED'), false],
        ];
    }

    #[Test]
    #[DataProvider('transitions')]
    public function it_enforces_the_lifecycle_state_machine(PurchaseOrderStatus $from, PurchaseOrderStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    #[Test]
    public function only_received_and_cancelled_are_final(): void
    {
        $this->assertFalse(PurchaseOrderStatus::Draft->isFinal());
        $this->assertFalse(PurchaseOrderStatus::Approved->isFinal());
        $this->assertTrue(PurchaseOrderStatus::Received->isFinal());
        $this->assertTrue(PurchaseOrderStatus::Cancelled->isFinal());

        $this->assertSame([], PurchaseOrderStatus::Received->allowedTransitions());
        $this->assertSame([], PurchaseOrderStatus::Cancelled->allowedTransitions());
    }

    #[Test]
    public function only_drafts_are_editable(): void
    {
        $editable = array_filter(PurchaseOrderStatus::cases(), fn ($status) => $status->isEditable());

        $this->assertSame([PurchaseOrderStatus::Draft], array_values($editable));
    }
}
