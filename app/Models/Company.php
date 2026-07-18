<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'timezone',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role_id', 'status', 'is_default'])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', 'active');
    }

    public function setting(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function centers(): HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function employmentRelationships(): HasMany
    {
        return $this->hasMany(EmploymentRelationship::class);
    }

    public function laborConditions(): HasMany
    {
        return $this->hasMany(LaborCondition::class);
    }

    public function workerCredentials(): HasMany
    {
        return $this->hasMany(WorkerCredential::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function shiftTemplates(): HasMany
    {
        return $this->hasMany(ShiftTemplate::class);
    }

    public function shiftTemplateSegments(): HasMany
    {
        return $this->hasMany(ShiftTemplateSegment::class);
    }

    public function scheduleProfiles(): HasMany
    {
        return $this->hasMany(ScheduleProfile::class);
    }

    public function scheduleBatches(): HasMany
    {
        return $this->hasMany(ScheduleBatch::class);
    }

    public function dailyScheduleAssignments(): HasMany
    {
        return $this->hasMany(DailyScheduleAssignment::class);
    }

    public function dailyScheduleSegments(): HasMany
    {
        return $this->hasMany(DailyScheduleSegment::class);
    }

    public function scheduleProfileWeeklyRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileWeeklyRule::class);
    }

    public function scheduleProfileCycleRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileCycleRule::class);
    }

    public function scheduleProfileFlexibleRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileFlexibleRule::class);
    }

    public function scheduleProfileOnCallRules(): HasMany
    {
        return $this->hasMany(ScheduleProfileOnCallRule::class);
    }

    public function scheduleProfileAssignments(): HasMany
    {
        return $this->hasMany(ScheduleProfileAssignment::class);
    }

    public function scheduleDays(): HasMany
    {
        return $this->hasMany(ScheduleDay::class);
    }

    public function scheduleBreaks(): HasMany
    {
        return $this->hasMany(ScheduleBreak::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function mandatoryRestDays(): HasMany
    {
        return $this->hasMany(MandatoryRestDay::class);
    }

    public static function defaultSettings(): array
    {
        return [
            'payroll_period_type' => 'biweekly',
            'default_timezone' => 'America/Mexico_City',
            'default_closure_day' => null,
            'allow_worker_corrections' => false,
            'require_pin_for_kiosk' => true,
            'require_pin_for_confirmation' => true,
            'metadata' => [],
        ];
    }

    public function timeEvents(): HasMany
    {
        return $this->hasMany(TimeEvent::class);
    }
    public function organizationalUnits(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    public function employmentUnitAssignments(): HasMany
    {
        return $this->hasMany(EmploymentUnitAssignment::class);
    }

    public function operationalScopeAssignments(): HasMany
    {
        return $this->hasMany(OperationalScopeAssignment::class);
    }
}
