<?php

namespace App\Models;

use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'import_type',
        'target_type',
        'target_id',
        'status',
        'existing_assignment_policy',
        'original_filename',
        'storage_disk',
        'storage_path',
        'file_sha256',
        'file_size_bytes',
        'encoding',
        'delimiter',
        'header_schema_version',
        'validation_sha256',
        'idempotency_key',
        'reason',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'warning_rows',
        'applied_rows',
        'skipped_rows',
        'validated_at',
        'applied_at',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'header_schema_version' => 'integer',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'warning_rows' => 'integer',
            'applied_rows' => 'integer',
            'skipped_rows' => 'integer',
            'validated_at' => 'datetime',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scheduleBatch(): BelongsTo
    {
        return $this->belongsTo(ScheduleBatch::class, 'target_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
