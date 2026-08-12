<?php

namespace App\Models;

use Database\Factories\AttendancePeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePeriod extends Model
{
    /** @use HasFactory<AttendancePeriodFactory> */
    use HasFactory;

    public const SCOPE_CENTER = 'center';
    public const SCOPE_ORGANIZATIONAL_UNITS = 'organizational_units';

    public const STATUS_OPEN = 'open';
    public const STATUS_READY = 'ready';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const SCOPES = [
        self::SCOPE_CENTER,
        self::SCOPE_ORGANIZATIONAL_UNITS,
    ];

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_READY,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'scope_type',
        'name',
        'period_start',
        'period_end',
        'timezone',
        'status',
        'notes',
        'cancellation_reason',
        'validation_summary',
        'report_summary',
        'snapshot_schema_version',
        'snapshot_canonical_json',
        'snapshot_sha256',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'cancelled_at' => 'datetime',
            'validated_at' => 'datetime',
            'closed_at' => 'datetime',
            'validation_summary' => 'array',
            'report_summary' => 'array',
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

    public function scopes(): HasMany
    {
        return $this->hasMany(AttendancePeriodScope::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
