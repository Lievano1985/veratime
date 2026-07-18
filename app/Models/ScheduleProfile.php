<?php

namespace App\Models;

use Database\Factories\ScheduleProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleProfile extends Model
{
    /** @use HasFactory<ScheduleProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'profile_type',
        'pattern_mode',
        'status',
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

    public function weeklyRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileWeeklyRule::class)->orderBy('day_of_week');
    }

    public function cycleRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileCycleRule::class)->orderBy('cycle_day');
    }

    public function flexibleRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileFlexibleRule::class)->orderBy('day_of_week');
    }

    public function onCallRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileOnCallRule::class)->orderBy('day_of_week');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleProfileAssignment::class);
    }
}
