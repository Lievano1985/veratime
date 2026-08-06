<?php

namespace App\Models;

use Database\Factories\WorkDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkDay extends Model
{
    /** @use HasFactory<WorkDayFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_WITH_ALERTS = 'with_alerts';
    public const STATUS_UNDER_REVIEW = 'under_review';

    public const SCHEDULE_STATUS_SCHEDULED = 'scheduled';
    public const SCHEDULE_STATUS_UNSCHEDULED = 'unscheduled';

    protected $fillable = [
        'company_id',
        'worker_id',
        'employment_relationship_id',
        'center_id',
        'schedule_batch_id',
        'daily_schedule_assignment_id',
        'work_date',
        'timezone',
        'status',
        'schedule_status',
        'day_type',
        'expected_work_minutes',
        'valid_time_event_count',
        'first_event_at_utc',
        'last_event_at_utc',
        'valid_time_event_ids',
        'active_calculation_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'expected_work_minutes' => 'integer',
            'valid_time_event_count' => 'integer',
            'first_event_at_utc' => 'immutable_datetime',
            'last_event_at_utc' => 'immutable_datetime',
            'valid_time_event_ids' => 'array',
            'active_calculation_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function employmentRelationship(): BelongsTo
    {
        return $this->belongsTo(EmploymentRelationship::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function scheduleBatch(): BelongsTo
    {
        return $this->belongsTo(ScheduleBatch::class);
    }

    public function dailyScheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(DailyScheduleAssignment::class);
    }

    public function activeCalculation(): BelongsTo
    {
        return $this->belongsTo(WorkDayCalculation::class, 'active_calculation_id');
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(WorkDayCalculation::class);
    }

    public function isUnscheduled(): bool
    {
        return $this->schedule_status === self::SCHEDULE_STATUS_UNSCHEDULED;
    }
}
