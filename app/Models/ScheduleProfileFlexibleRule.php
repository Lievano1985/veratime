<?php

namespace App\Models;

use Database\Factories\ScheduleProfileFlexibleRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleProfileFlexibleRule extends Model
{
    /** @use HasFactory<ScheduleProfileFlexibleRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'day_type',
        'required_minutes',
        'window_start_local_time',
        'window_end_local_time',
        'window_start_day_offset',
        'window_end_day_offset',
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
