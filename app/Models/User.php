<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 社員。管理者と作業者がいる（D-004）
 */
#[Fillable(['name', 'role', 'password', 'is_active', 'is_dummy'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_dummy' => 'boolean',
        ];
    }

    /**
     * この社員が担当している製造指示
     *
     * @return HasMany<ProductionOrder, $this>
     */
    public function assignedOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'assignee_id');
    }
}
