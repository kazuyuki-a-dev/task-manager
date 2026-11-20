<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Company;
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

        // 取引会社4社。本物の会社名は使わず、かならず「ダミー」を付ける（D-033）
        $companies = collect(['ダミー東和工業', 'ダミー北陸製作所', 'ダミー南海精工', 'ダミー中央機械'])
            ->map(fn (string $name) => Company::factory()->dummy()->create(['name' => $name]));

        // 製品15件。どれもどこか1社の製品。品番のないものも混ぜる（D-019）
        $products = Product::factory()->count(12)->dummy()
            ->state(fn () => ['company_id' => $companies->random()->id])->create()
            ->merge(Product::factory()->count(3)->withoutCode()->dummy()
                ->state(fn () => ['company_id' => $companies->random()->id])->create());

        $counts = [
            OrderStatus::NotStarted->value => 25,
            OrderStatus::InProgress->value => 15,
            OrderStatus::Done->value => 40,
        ];

        foreach ($counts as $status => $count) {
            $status = OrderStatus::from($status);

            // 完了した指示は、納期を過去にする（未来の日に完了したことにならないように）
            $past = $status === OrderStatus::Done
                ? function () {
                    $due = now()->subDays(fake()->numberBetween(6, 60))->startOfDay();

                    return ['due_date' => $due, 'material_ready_date' => $due->copy()->subDays(fake()->numberBetween(2, 7))];
                }
                : [];

            ProductionOrder::factory()
                ->count($count)
                ->dummy()
                ->state($past)
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
