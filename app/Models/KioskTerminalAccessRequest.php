<?php

namespace App\Models;

use Database\Factories\KioskTerminalAccessRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskTerminalAccessRequest extends Model
{
    /** @use HasFactory<KioskTerminalAccessRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'public_id',
        'request_secret_hash',
        'requested_name',
        'status',
        'center_id',
        'expires_at',
        'requested_ip',
        'requested_user_agent',
        'reviewed_by_user_id',
        'reviewed_at',
        'claimed_at',
        'kiosk_device_id',
    ];

    protected $hidden = [
        'request_secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function kioskDevice(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class);
    }
}
