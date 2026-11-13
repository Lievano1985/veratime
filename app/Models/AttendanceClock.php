<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttendanceClock extends Model
{
    protected $fillable = ['company_id', 'center_id', 'name', 'serial'];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'attendance_clock_workers')->withPivot('clock_user_id');
    }
}
