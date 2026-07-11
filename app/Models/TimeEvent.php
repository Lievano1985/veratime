<?php

namespace App\Models;

use Database\Factories\TimeEventFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEvent extends Model
{
    /** @use HasFactory<TimeEventFactory> */
    use HasFactory;

    public const EVENT_TYPES = [
        'clock_in',
        'clock_out',
        'break_start',
        'break_end',
        'manual_entry',
        'logical_void',
    ];

    public const SOURCES = [
        'web',
        'pwa',
        'kiosk',
        'api',
        'csv',
        'admin_manual',
        'job',
        'integration',
    ];

    public const STATUSES = [
        'valid',
        'pending_review',
        'voided',
        'replaced',
        'ignored',
    ];

    protected $fillable = [
        'event_type',
        'occurred_at_utc',
        'occurred_local_date',
        'occurred_local_time',
        'timezone',
        'received_at',
        'source',
        'external_id',
        'idempotency_key',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at_utc' => 'immutable_datetime',
            'occurred_local_date' => 'date',
            'received_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected function occurredLocalTime(): Attribute
    {
        return Attribute::get(
            fn (?string $value): ?string => $value === null ? null : substr($value, 0, 8),
        );
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

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }
}