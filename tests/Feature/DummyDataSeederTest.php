<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
        $this->assertGreaterThan(0, ProductionOrder::count());

        $this->assertSame(0, User::where('is_dummy', false)->count());
        $this->assertSame(0, ProductionOrder::where('is_dummy', false)->count());
    }
}
