<?php

namespace App\Models;

use Database\Factories\EmploymentRelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentRelationship extends Model
{
    /** @use HasFactory<EmploymentRelationshipFactory> */
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'center_id',
        'position_name',
        'started_at',
        'ended_at',
        'status',
        'source',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'metadata' => 'array',
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

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
