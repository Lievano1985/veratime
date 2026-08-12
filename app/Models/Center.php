<?php

namespace App\Models;

use Database\Factories\CenterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Center extends Model
{
    /** @use HasFactory<CenterFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'timezone',
        'status',
        'address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employmentRelationships(): HasMany
    {
        return $this->hasMany(EmploymentRelationship::class);
    }

    public function timeEvents(): HasMany
    {
        return $this->hasMany(TimeEvent::class);
    }
    public function organizationalUnits(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    public function operationalScopeAssignments(): HasMany
    {
        return $this->hasMany(OperationalScopeAssignment::class);
    }

    public function scheduleProfileAssignments(): HasMany
    {
        return $this->hasMany(ScheduleProfileAssignment::class);
    }

    public function scheduleBatches(): HasMany
    {
        return $this->hasMany(ScheduleBatch::class);
    }

    public function attendancePeriods(): HasMany
    {
        return $this->hasMany(AttendancePeriod::class);
    }
}
