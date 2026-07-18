<?php

namespace App\Models;

use Database\Factories\ScheduleBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleBatch extends Model
{
    /** @use HasFactory<ScheduleBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'version',
        'status',
        'creation_source',
        'notes',
        'snapshot_schema_version',
        'snapshot_canonical_json',
        'snapshot_sha256',
        'published_at',
        'superseded_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'version' => 'integer',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function previousBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_batch_id');
    }

    public function supersededByBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function dailyAssignments(): HasMany
    {
        return $this->hasMany(DailyScheduleAssignment::class);
    }
}
