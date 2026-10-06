<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileOfflineMarkingAuthorization extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'company_id', 'user_id', 'worker_id', 'mobile_marking_policy_id', 'mobile_device_binding_id',
        'status', 'policy_version', 'issued_monotonic_milliseconds', 'max_clock_drift_seconds',
        'issued_at', 'expires_at', 'policy_snapshot', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'policy_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $authorization): void {
            $authorization->public_id ??= (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(MobileMarkingPolicy::class, 'mobile_marking_policy_id');
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(MobileDeviceBinding::class, 'mobile_device_binding_id');
    }
}
