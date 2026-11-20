<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    /**
     * ふだんは未着手の指示を作る
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $due = Carbon::instance(fake()->dateTimeBetween('-5 days', '+20 days'))->startOfDay();

        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 500),
            // 材料は納期の数日前にそろう想定
            'material_ready_date' => $due->copy()->subDays(fake()->numberBetween(2, 7)),
            'due_date' => $due,
            'status' => OrderStatus::NotStarted,
            'started_at' => null,
            'completed_at' => null,
            'is_dummy' => false,
        ];
    }

    /**
     * 状態に合わせて、始めた日時・完了した日時も入れる（D-017）
     */
    public function status(OrderStatus $status): static
    {
        return $this->state(function (array $attributes) use ($status) {
            $due = Carbon::parse($attributes['due_date']);
            $started = $due->copy()->subDays(fake()->numberBetween(1, 5))->setTime(fake()->numberBetween(8, 16), 0);

            return [
                'status' => $status,
                'started_at' => $status === OrderStatus::NotStarted ? null : $started,
                'completed_at' => $status === OrderStatus::Done
                    ? $started->copy()->addDays(fake()->numberBetween(0, 4))->setTime(17, 0)
                    : null,
            ];
        });
    }

    /**
     * ダミーデータの印を付ける
     */
    public function dummy(): static
    {
        return $this->state(fn () => ['is_dummy' => true]);
    }
}
