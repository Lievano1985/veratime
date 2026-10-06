<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileDeviceBindingAuthorization extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CHALLENGED = 'challenged';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id', 'user_id', 'worker_id', 'created_by_user_id', 'public_id',
        'authorization_secret_hash', 'status', 'expires_at', 'challenge_hash', 'challenge_encrypted',
        'requested_device_name', 'challenge_issued_at', 'challenge_expires_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'challenge_issued_at' => 'immutable_datetime',
            'challenge_expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
