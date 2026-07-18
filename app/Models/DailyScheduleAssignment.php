<?php

namespace App\Models;

use Database\Factories\DailyScheduleAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyScheduleAssignment extends Model
{
    /** @use HasFactory<DailyScheduleAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'work_date',
        'day_type',
        'timezone',
        'source_type',
        'source_reference',
        'required_minutes',
        'window_start_local_time',
        'window_end_local_time',
        'window_start_day_offset',
        'window_end_day_offset',
        'availability_start_local_time',
        'availability_end_local_time',
        'availability_start_day_offset',
        'availability_end_day_offset',
        'max_work_minutes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'source_reference' => 'array',
            'required_minutes' => 'integer',
            'window_start_day_offset' => 'integer',
            'window_end_day_offset' => 'integer',
            'availability_start_day_offset' => 'integer',
            'availability_end_day_offset' => 'integer',
            'max_work_minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scheduleBatch(): BelongsTo
    {
        return $this->belongsTo(ScheduleBatch::class);
    }

    public function employmentRelationship(): BelongsTo
    {
        return $this->belongsTo(EmploymentRelationship::class);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(DailyScheduleSegment::class)->orderBy('segment_order');
    }
}
