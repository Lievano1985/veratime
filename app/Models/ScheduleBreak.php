<?php

namespace App\Models;

use Database\Factories\ScheduleBreakFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleBreak extends Model
{
    /** @use HasFactory<ScheduleBreakFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_day_id',
        'name',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_paid',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'is_paid' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scheduleDay(): BelongsTo
    {
        return $this->belongsTo(ScheduleDay::class);
    }
}
