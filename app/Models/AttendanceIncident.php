<?php

namespace App\Models;

use Database\Factories\AttendanceIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceIncident extends Model
{
    /** @use HasFactory<AttendanceIncidentFactory> */
    use HasFactory;

    public const TYPE_VACATION = 'vacation';
    public const TYPE_INCAPACITY = 'incapacity';
    public const TYPE_PAID_PERMISSION = 'paid_permission';
    public const TYPE_UNPAID_PERMISSION = 'unpaid_permission';
    public const TYPE_JUSTIFIED_PAID_ABSENCE = 'justified_paid_absence';
    public const TYPE_JUSTIFIED_UNPAID_ABSENCE = 'justified_unpaid_absence';
    public const TYPE_UNJUSTIFIED_ABSENCE = 'unjustified_absence';
    public const TYPE_MATERNITY_PATERNITY = 'maternity_paternity';
    public const TYPE_OTHER = 'other';

    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'worker_id',
        'employment_relationship_id',
        'start_date',
        'end_date',
        'incident_type',
        'payment_status',
        'status',
        'reference',
        'notes',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cancelled_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_VACATION,
            self::TYPE_INCAPACITY,
            self::TYPE_PAID_PERMISSION,
            self::TYPE_UNPAID_PERMISSION,
            self::TYPE_JUSTIFIED_PAID_ABSENCE,
            self::TYPE_JUSTIFIED_UNPAID_ABSENCE,
            self::TYPE_UNJUSTIFIED_ABSENCE,
            self::TYPE_MATERNITY_PATERNITY,
            self::TYPE_OTHER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function paymentStatuses(): array
    {
        return [
            self::PAYMENT_PAID,
            self::PAYMENT_UNPAID,
            self::PAYMENT_NOT_APPLICABLE,
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

    public function employmentRelationship(): BelongsTo
    {
        return $this->belongsTo(EmploymentRelationship::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
