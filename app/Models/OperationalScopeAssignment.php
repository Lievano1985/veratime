<?php

namespace App\Models;

use Database\Factories\OperationalScopeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalScopeAssignment extends Model
{
    /** @use HasFactory<OperationalScopeAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'center_id',
        'organizational_unit_id',
        'responsibility_type',
        'effective_from',
        'effective_to',
        'status',
        'source',
        'reason',
        'replaced_by_id',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}