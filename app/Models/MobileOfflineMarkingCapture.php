<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileOfflineMarkingCapture extends Model
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    protected $fillable = [
        'company_id', 'user_id', 'worker_id', 'time_event_id', 'mobile_offline_marking_authorization_id',
        'mobile_marking_policy_id', 'mobile_device_binding_id', 'client_event_id', 'event_type', 'timezone',
        'offline_authorization_public_id', 'binding_public_id', 'policy_public_id', 'policy_version',
        'occurred_at_claimed', 'occurred_at_raw', 'monotonic_elapsed_milliseconds', 'estimated_occurred_at',
        'status', 'review_reason', 'signature', 'payload_hash', 'policy_snapshot', 'location_latitude',
        'location_longitude', 'location_accuracy_meters', 'location_captured_at', 'location_captured_at_raw',
        'location_is_mocked', 'distance_meters',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at_claimed' => 'immutable_datetime',
            'estimated_occurred_at' => 'immutable_datetime',
            'policy_snapshot' => 'array',
            'location_latitude' => 'decimal:7',
            'location_longitude' => 'decimal:7',
            'location_accuracy_meters' => 'decimal:2',
            'location_captured_at' => 'immutable_datetime',
            'location_is_mocked' => 'boolean',
            'distance_meters' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(TimeEvent::class, 'time_event_id');
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(MobileOfflineMarkingAuthorization::class, 'mobile_offline_marking_authorization_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(MobileMarkingPolicy::class, 'mobile_marking_policy_id');
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(MobileDeviceBinding::class, 'mobile_device_binding_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
