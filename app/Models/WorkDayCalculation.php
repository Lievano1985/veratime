<?php

namespace App\Models;

use Database\Factories\WorkDayCalculationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDayCalculation extends Model
{
    /** @use HasFactory<WorkDayCalculationFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_INVALIDATED = 'invalidated';

    public const GENERATED_BY_SYSTEM = 'system';
    public const GENERATED_BY_USER = 'user';
    public const GENERATED_BY_JOB = 'job';

    public const CLASSIFICATION_PENDING = 'pending';

    protected $fillable = [
        'company_id',
        'work_day_id',
        'version',
        'status',
        'calculated_at',
        'generated_by_type',
        'generated_by_id',
        'reason',
        'total_work_minutes',
        'ordinary_minutes',
        'night_minutes',
        'overtime_minutes',
        'break_minutes',
        'paid_break_minutes',
        'sunday_minutes',
        'mandatory_rest_minutes',
        'classification',
        'rules_snapshot',
        'inputs_snapshot',
        'result_snapshot',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'calculated_at' => 'immutable_datetime',
            'generated_by_id' => 'integer',
            'total_work_minutes' => 'integer',
            'ordinary_minutes' => 'integer',
            'night_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'break_minutes' => 'integer',
            'paid_break_minutes' => 'integer',
            'sunday_minutes' => 'integer',
            'mandatory_rest_minutes' => 'integer',
            'rules_snapshot' => 'array',
            'inputs_snapshot' => 'array',
            'result_snapshot' => 'array',
            'explanation' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workDay(): BelongsTo
    {
        return $this->belongsTo(WorkDay::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }
}
