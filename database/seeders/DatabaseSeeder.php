<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * 開発と画面の確認に使うダミーデータ。
 * すべてに is_dummy = true の印を付け、画面にも「ダミー」と出す。
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 職場の人数（約20人）に合わせて、管理者1人・作業者19人
        User::factory()->admin()->dummy()->create(['name' => 'ダミー管理者']);
        $workers = User::factory()->count(19)->dummy()->create();

        // 製品15件。品番のないものも混ぜる（D-019）
        $products = Product::factory()->count(12)->dummy()->create()
            ->merge(Product::factory()->count(3)->withoutCode()->dummy()->create());

        $counts = [
            OrderStatus::NotStarted->value => 25,
            OrderStatus::InProgress->value => 15,
            OrderStatus::Done->value => 40,
        ];

        foreach ($counts as $status => $count) {
            $status = OrderStatus::from($status);

            ProductionOrder::factory()
                ->count($count)
                ->dummy()
                ->status($status)
                ->state(fn () => ['product_id' => $products->random()->id])
                ->create()
                ->each(function (ProductionOrder $order) use ($status, $workers) {
                    // 未着手のうち、いくつかは担当をまだ決めていない
                    if ($status === OrderStatus::NotStarted && fake()->boolean(20)) {
                        return;
                    }
                    // 1〜3人で取りかかる（D-018）
                    $order->assignees()->attach($workers->random(fake()->numberBetween(1, 3))->pluck('id'));
                });
        }
    }
}
