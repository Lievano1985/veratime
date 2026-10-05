<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileMarkingTimeReference extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'worker_id',
        'mobile_marking_policy_id',
        'policy_version',
        'issued_at',
        'expires_at',
        'schema_version',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reference): void {
            $reference->public_id ??= (string) Str::uuid();
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
}
