<?php

namespace App\Models;

use Database\Factories\CenterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Center extends Model
{
    /** @use HasFactory<CenterFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'timezone',
        'status',
        'address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
