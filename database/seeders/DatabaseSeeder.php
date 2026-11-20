<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
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

        $counts = [
            OrderStatus::NotStarted->value => 25,
            OrderStatus::InProgress->value => 15,
            OrderStatus::Done->value => 20,
        ];

        foreach ($counts as $status => $count) {
            ProductionOrder::factory()
                ->count($count)
                ->dummy()
                ->status(OrderStatus::from($status))
                // 未着手のうち、いくつかは担当をまだ決めていない
                ->state(fn () => [
                    'assignee_id' => $status === OrderStatus::NotStarted->value && fake()->boolean(20)
                        ? null
                        : $workers->random()->id,
                ])
                ->create();
        }
    }
}
