<?php

namespace App\Models;

use Database\Factories\LegalRuleVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalRuleVersion extends Model
{
    /** @use HasFactory<LegalRuleVersionFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'legal_rule_id',
        'version',
        'value',
        'unit',
        'source_reference',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'value' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'created_by' => 'integer',
        ];
    }

    public function legalRule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
