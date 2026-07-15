<?php

namespace App\Models;

use Database\Factories\OrganizationalUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalUnit extends Model
{
    /** @use HasFactory<OrganizationalUnitFactory> */
    use HasFactory;

    protected $fillable = [
        'center_id',
        'parent_id',
        'code',
        'name',
        'type',
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

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function employmentUnitAssignments(): HasMany
    {
        return $this->hasMany(EmploymentUnitAssignment::class);
    }

    public function operationalScopeAssignments(): HasMany
    {
        return $this->hasMany(OperationalScopeAssignment::class);
    }

    public function scheduleProfileAssignments(): HasMany
    {
        return $this->hasMany(ScheduleProfileAssignment::class);
    }
}
