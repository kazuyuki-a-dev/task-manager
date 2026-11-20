<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    /**
     * 本物の品名は使わない（要件定義書 8. 守るもの）
     */
    private const PRODUCTS = ['ブラケット', 'シャフト', 'フランジ', 'ギアカバー', 'ベースプレート', 'ハウジング', 'スペーサー', 'ステー'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $due = fake()->dateTimeBetween('-5 days', '+20 days');

        return [
            'product_name' => fake()->randomElement(self::PRODUCTS).'-'.fake()->numerify('###'),
            'quantity' => fake()->numberBetween(1, 500),
            // 材料は納期の数日前にそろう想定
            'material_ready_date' => (clone $due)->modify('-'.fake()->numberBetween(2, 7).' days'),
            'due_date' => $due,
            'assignee_id' => null,
            'status' => OrderStatus::NotStarted,
            'is_dummy' => false,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /**
     * ダミーデータの印を付ける
     */
    public function dummy(): static
    {
        return $this->state(fn () => ['is_dummy' => true]);
    }
}
