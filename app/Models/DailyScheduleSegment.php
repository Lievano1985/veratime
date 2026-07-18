<?php

namespace App\Models;

use Database\Factories\DailyScheduleSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyScheduleSegment extends Model
{
    /** @use HasFactory<DailyScheduleSegmentFactory> */
    use HasFactory;

    protected $fillable = [
        'segment_order',
        'segment_type',
        'timing_mode',
        'start_local_time',
        'end_local_time',
        'start_day_offset',
        'end_day_offset',
        'starts_at_utc',
        'ends_at_utc',
        'duration_minutes',
        'is_paid',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'segment_order' => 'integer',
            'start_day_offset' => 'integer',
            'end_day_offset' => 'integer',
            'starts_at_utc' => 'datetime',
            'ends_at_utc' => 'datetime',
            'duration_minutes' => 'integer',
            'is_paid' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function dailyScheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(DailyScheduleAssignment::class);
    }

    public function shiftTemplateSegment(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplateSegment::class);
    }
}
