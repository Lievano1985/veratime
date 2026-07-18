<?php

namespace App\Models;

use Database\Factories\ScheduleProfileAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleProfileAssignment extends Model
{
    /** @use HasFactory<ScheduleProfileAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'assignment_scope',
        'center_id',
        'organizational_unit_id',
        'employment_relationship_id',
        'effective_from',
        'effective_to',
        'status',
        'source',
        'reason',
        'replaced_by_id',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
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

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function employmentRelationship(): BelongsTo
    {
        return $this->belongsTo(EmploymentRelationship::class);
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
