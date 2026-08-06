<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_PENDING_INFORMATION = 'pending_information';
    public const STATUS_JUSTIFIED = 'justified';
    public const STATUS_CORRECTED = 'corrected';
    public const STATUS_CLOSED = 'closed';

    public const OPEN_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_REVIEW,
        self::STATUS_PENDING_INFORMATION,
    ];

    protected $fillable = [
        'company_id',
        'alert_type_id',
        'worker_id',
        'work_day_id',
        'work_day_calculation_id',
        'severity',
        'status',
        'title',
        'description',
        'rule_code',
        'detected_at',
        'assigned_to',
        'due_at',
        'resolution',
        'resolved_by',
        'resolved_at',
        'fingerprint',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function alertType(): BelongsTo
    {
        return $this->belongsTo(AlertType::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function workDay(): BelongsTo
    {
        return $this->belongsTo(WorkDay::class);
    }

    public function workDayCalculation(): BelongsTo
    {
        return $this->belongsTo(WorkDayCalculation::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
