<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KioskDevice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'center_id',
        'name',
        'status',
        'pairing_code_hash',
        'pairing_expires_at',
        'paired_at',
        'device_token_hash',
        'created_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
        'last_seen_at',
        'last_seen_ip',
        'last_seen_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'pairing_expires_at' => 'immutable_datetime',
            'paired_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function isOnline(): bool
    {
        return $this->status === 'active'
            && $this->last_seen_at?->greaterThanOrEqualTo(now()->subMinutes(3));
    }
}
