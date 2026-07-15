<?php

namespace App\Models;

use Database\Factories\ShiftTemplateSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTemplateSegment extends Model
{
    /** @use HasFactory<ShiftTemplateSegmentFactory> */
    use HasFactory;

    protected $fillable = [
        'segment_type',
        'timing_mode',
        'start_local_time',
        'end_local_time',
        'start_day_offset',
        'end_day_offset',
        'duration_minutes',
        'is_paid',
        'is_required',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_required' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }
}
