<?php

namespace App\Models;

use Database\Factories\MandatoryRestDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MandatoryRestDay extends Model
{
    /** @use HasFactory<MandatoryRestDayFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'scope',
        'source',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
