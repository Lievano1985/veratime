<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_addon',
        'requires_product_id',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_addon' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function requiresProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'requires_product_id');
    }

    public function dependentProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'requires_product_id');
    }

    public function customerAccountProducts(): HasMany
    {
        return $this->hasMany(CustomerAccountProduct::class);
    }

    public function customerAccounts(): BelongsToMany
    {
        return $this->belongsToMany(CustomerAccount::class, 'customer_account_products')
            ->using(CustomerAccountProduct::class)
            ->withPivot(['status', 'starts_at', 'trial_ends_at', 'ends_at', 'metadata'])
            ->withTimestamps();
    }
}
