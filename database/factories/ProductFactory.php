<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * 本物の品名は使わない（要件定義書 8. 守るもの）
     */
    private const NAMES = ['ブラケット', 'シャフト', 'フランジ', 'ギアカバー', 'ベースプレート', 'ハウジング', 'スペーサー', 'ステー'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??-####')),
            'name' => fake()->randomElement(self::NAMES).fake()->numerify('-###'),
            'is_dummy' => false,
        ];
    }

    /**
     * 品番のない製品
     */
    public function withoutCode(): static
    {
        return $this->state(fn () => ['code' => null]);
    }

    /**
     * ダミーデータの印を付ける
     */
    public function dummy(): static
    {
        return $this->state(fn () => ['is_dummy' => true]);
    }
}
