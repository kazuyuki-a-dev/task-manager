<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 状態をどこからどこへ変えてよいか（R-04・D-024）
 */
class OrderStatusTransitionTest extends TestCase
{
    /**
     * 3つの状態の、すべての組み合わせ（9通り）
     *
     * @return array<string, array{OrderStatus, OrderStatus, bool}>
     */
    public static function transitions(): array
    {
        $allowed = [
            'not_started>in_progress',
            'in_progress>done',
            'in_progress>not_started',
            'done>in_progress',
        ];

        $cases = [];
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $key = "{$from->value}>{$to->value}";
                $cases[$key] = [$from, $to, in_array($key, $allowed, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_can_change_to(OrderStatus $from, OrderStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canChangeTo($to));
    }
}
