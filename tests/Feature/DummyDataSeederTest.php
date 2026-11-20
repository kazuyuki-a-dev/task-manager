<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ダミーデータには、すべて印が付いていること
 */
class DummyDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_seeded_data_is_marked_as_dummy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(20, User::count());
        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertGreaterThan(0, Product::count());
        $this->assertGreaterThan(0, ProductionOrder::count());

        $this->assertSame(0, User::where('is_dummy', false)->count());
        $this->assertSame(0, Product::where('is_dummy', false)->count());
        $this->assertSame(0, ProductionOrder::where('is_dummy', false)->count());
    }

    /**
     * 状態と、始めた日時・完了した日時がくいちがわないこと（D-017）
     */
    public function test_seeded_dates_match_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, ProductionOrder::where('status', OrderStatus::NotStarted)
            ->where(fn ($q) => $q->whereNotNull('started_at')->orWhereNotNull('completed_at'))->count());
        $this->assertSame(0, ProductionOrder::where('status', OrderStatus::InProgress)
            ->where(fn ($q) => $q->whereNull('started_at')->orWhereNotNull('completed_at'))->count());
        $this->assertSame(0, ProductionOrder::where('status', OrderStatus::Done)
            ->where(fn ($q) => $q->whereNull('started_at')->orWhereNull('completed_at'))->count());
        $this->assertSame(0, ProductionOrder::whereColumn('completed_at', '<', 'started_at')->count());
    }
}
