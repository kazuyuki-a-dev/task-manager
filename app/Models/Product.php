<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 製品。品番は空でもよい（D-019）
 */
#[Fillable(['code', 'name', 'is_dummy'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dummy' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductionOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
