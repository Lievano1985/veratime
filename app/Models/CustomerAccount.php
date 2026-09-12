<?php

namespace App\Models;

use Database\Factories\CustomerAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerAccount extends Model
{
    /** @use HasFactory<CustomerAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'account_type',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function customerAccountProducts(): HasMany
    {
        return $this->hasMany(CustomerAccountProduct::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'customer_account_products')
            ->using(CustomerAccountProduct::class)
            ->withPivot(['status', 'starts_at', 'trial_ends_at', 'ends_at', 'metadata'])
            ->withTimestamps();
    }
}
