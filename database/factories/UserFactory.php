<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * ふだんは作業者（パスワードなし）を作る
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('ja_JP')->name(),
            'role' => UserRole::Worker,
            'password' => null,
            'is_active' => true,
            'is_dummy' => false,
        ];
    }

    /**
     * 管理者
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
            'password' => 'password',
        ]);
    }

    /**
     * 辞めた人など、使えなくした社員
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * ダミーデータの印を付ける
     */
    public function dummy(): static
    {
        return $this->state(fn () => ['is_dummy' => true]);
    }
}
