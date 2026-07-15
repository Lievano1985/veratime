<?php

namespace App\Models;

use Database\Factories\ShiftTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplate extends Model
{
    /** @use HasFactory<ShiftTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(ShiftTemplateSegment::class)->orderBy('sort_order');
    }

    public function metrics(): array
    {
        return \App\Domains\Scheduling\Support\ShiftTemplateTimeline::fromSegments($this->segments)->metrics();
    }
}
