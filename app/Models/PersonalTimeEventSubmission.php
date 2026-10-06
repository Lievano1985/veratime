<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalTimeEventSubmission extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'worker_id',
        'time_event_id',
        'client_event_id',
        'payload_hash',
    ];

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

    public function timeEvent(): BelongsTo
    {
        return $this->belongsTo(TimeEvent::class);
    }
}
