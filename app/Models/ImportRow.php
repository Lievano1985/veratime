<?php

namespace App\Models;

use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    protected $fillable = [
        'row_number',
        'status',
        'raw_data',
        'normalized_data',
        'errors',
        'warnings',
        'work_date',
        'row_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'normalized_data' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'work_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function employmentRelationship(): BelongsTo
    {
        return $this->belongsTo(EmploymentRelationship::class);
    }

    public function existingDailyScheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(DailyScheduleAssignment::class, 'existing_daily_schedule_assignment_id');
    }

    public function appliedDailyScheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(DailyScheduleAssignment::class, 'applied_daily_schedule_assignment_id');
    }
}
