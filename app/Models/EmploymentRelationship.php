<?php

namespace App\Models;

use Database\Factories\EmploymentRelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmploymentRelationship extends Model
{
    /** @use HasFactory<EmploymentRelationshipFactory> */
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'center_id',
        'position_name',
        'started_at',
        'ended_at',
        'status',
        'source',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
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

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function laborConditions(): HasMany
    {
        return $this->hasMany(LaborCondition::class);
    }

    public function activeLaborCondition(): HasOne
    {
        return $this->hasOne(LaborCondition::class)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->latest('effective_from');
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function timeEvents(): HasMany
    {
        return $this->hasMany(TimeEvent::class);
    }
    public function employmentUnitAssignments(): HasMany
    {
        return $this->hasMany(EmploymentUnitAssignment::class);
    }

    public function scheduleProfileAssignments(): HasMany
    {
        return $this->hasMany(ScheduleProfileAssignment::class);
    }

    public function dailyScheduleAssignments(): HasMany
    {
        return $this->hasMany(DailyScheduleAssignment::class);
    }
}
