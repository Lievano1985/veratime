<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMarkingEventEvidence extends Model
{
    protected $table = 'mobile_marking_event_evidences';

    protected $fillable = [
        'company_id', 'user_id', 'worker_id', 'time_event_id', 'mobile_marking_policy_id',
        'mobile_device_binding_id', 'mobile_marking_time_reference_id', 'policy_public_id',
        'policy_version', 'schema_version', 'signature', 'payload_hash', 'policy_snapshot',
        'location_latitude', 'location_longitude', 'location_accuracy_meters',
        'location_captured_at', 'location_is_mocked', 'distance_meters',
    ];

    protected function casts(): array
    {
        return [
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

    public function policy(): BelongsTo
    {
        return $this->belongsTo(MobileMarkingPolicy::class, 'mobile_marking_policy_id');
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(MobileDeviceBinding::class, 'mobile_device_binding_id');
    }

    public function timeReference(): BelongsTo
    {
        return $this->belongsTo(MobileMarkingTimeReference::class, 'mobile_marking_time_reference_id');
    }
}
