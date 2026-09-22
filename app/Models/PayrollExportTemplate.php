<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollExportTemplate extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'description',
        'status',
        'is_default',
        'delimiter',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(PayrollExportTemplateColumn::class)->orderBy('position');
    }
}
