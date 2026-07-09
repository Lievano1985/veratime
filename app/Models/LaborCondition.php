<?php

namespace App\Models;

use Database\Factories\LaborConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborCondition extends Model
{
    /** @use HasFactory<LaborConditionFactory> */
    use HasFactory;

    protected $fillable = [
        'employment_relationship_id',
        'schedule_id',
        'work_modality',
        'weekly_hours',
        'rest_day_of_week',
        'policy_id',
        'effective_from',
        'effective_to',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employmentRelationship(): BelongsTo
    {
        return $this->belongsTo(EmploymentRelationship::class);
    }
}
