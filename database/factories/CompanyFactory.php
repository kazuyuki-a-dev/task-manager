<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * 本物の会社名は使わない（要件定義書 8. 守るもの）。かならず「ダミー」を付ける
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // テストでは会社をたくさん作ることがあるので、番号で重ならないようにする
            'name' => fake()->unique()->numerify('ダミー工業#####'),
            'is_dummy' => false,
        ];
    }

    /**
     * ダミーデータの印を付ける
     */
    public function dummy(): static
    {
        return $this->state(fn () => ['is_dummy' => true]);
    }
}
