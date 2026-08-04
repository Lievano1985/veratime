<?php

namespace App\Models;

use Database\Factories\LegalParameterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalParameter extends Model
{
    /** @use HasFactory<LegalParameterFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'code',
        'value',
        'effective_from',
        'effective_to',
        'status',
        'source_reference',
        'reason',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'value' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
