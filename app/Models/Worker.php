<?php

namespace App\Models;

use Database\Factories\WorkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Worker extends Model
{
    /** @use HasFactory<WorkerFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'full_name',
        'email',
        'phone',
        'curp',
        'rfc',
        'status',
        'source',
        'external_id',
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

    public function employmentRelationships(): HasMany
    {
        return $this->hasMany(EmploymentRelationship::class);
    }

    public function activeEmploymentRelationship(): HasOne
    {
        return $this->hasOne(EmploymentRelationship::class)
            ->where('status', 'active')
            ->latest('started_at');
    }

    public function credential(): HasOne
    {
        return $this->hasOne(WorkerCredential::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }
}
