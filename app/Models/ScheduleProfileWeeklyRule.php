<?php

namespace App\Models;

use Database\Factories\ScheduleProfileWeeklyRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleProfileWeeklyRule extends Model
{
    /** @use HasFactory<ScheduleProfileWeeklyRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'day_type',
        'shift_template_id',
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

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }
}
