<?php

namespace App\Models;

use Database\Factories\ScheduleDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleDay extends Model
{
    /** @use HasFactory<ScheduleDayFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'day_of_week',
        'is_working_day',
        'start_time',
        'end_time',
        'crosses_midnight',
    ];

    protected function casts(): array
    {
        return [
            'is_working_day' => 'boolean',
            'crosses_midnight' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ScheduleBreak::class);
    }
}
