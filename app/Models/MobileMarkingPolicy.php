<?php

namespace App\Models;

use Database\Factories\MobileMarkingPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileMarkingPolicy extends Model
{
    /** @use HasFactory<MobileMarkingPolicyFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const MODE_FREE = 'free';

    public const MODE_CIRCLE = 'circle';

    protected $fillable = [
        'company_id',
        'center_id',
        'public_id',
        'status',
        'version',
        'mode',
        'requires_device_binding',
        'requires_biometric_unlock',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'max_accuracy_meters',
        'max_location_age_seconds',
        'valid_from',
        'valid_until',
        'offline_valid_until',
    ];

    protected function casts(): array
    {
        return [
            'requires_device_binding' => 'boolean',
            'requires_biometric_unlock' => 'boolean',
            'center_latitude' => 'decimal:7',
            'center_longitude' => 'decimal:7',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'offline_valid_until' => 'immutable_datetime',
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

    protected static function booted(): void
    {
        static::creating(function (self $policy): void {
            $policy->public_id ??= (string) Str::uuid();
        });
    }
}
