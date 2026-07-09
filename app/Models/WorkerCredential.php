<?php

namespace App\Models;

use Database\Factories\WorkerCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerCredential extends Model
{
    /** @use HasFactory<WorkerCredentialFactory> */
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'access_code',
        'status',
        'failed_attempts',
        'last_used_at',
        'last_changed_at',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
