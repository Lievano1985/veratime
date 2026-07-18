<?php

namespace App\Models;

use Database\Factories\ScheduleProfileOnCallRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleProfileOnCallRule extends Model
{
    /** @use HasFactory<ScheduleProfileOnCallRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'day_type',
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
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scheduleProfile(): BelongsTo
    {
        return $this->belongsTo(ScheduleProfile::class);
    }
}
