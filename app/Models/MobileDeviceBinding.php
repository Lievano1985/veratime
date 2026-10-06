<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileDeviceBinding extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = ['company_id', 'user_id', 'worker_id', 'mobile_device_binding_authorization_id', 'public_id', 'device_name', 'algorithm', 'public_key_spki', 'key_fingerprint', 'status', 'activated_at', 'revoked_at', 'revoked_by_user_id'];

    protected function casts(): array
    {
        return ['activated_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $binding) => $binding->public_id ??= (string) Str::uuid());
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

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(MobileDeviceBindingAuthorization::class, 'mobile_device_binding_authorization_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
