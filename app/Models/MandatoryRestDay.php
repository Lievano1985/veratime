<?php

namespace App\Models;

use Database\Factories\MandatoryRestDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MandatoryRestDay extends Model
{
    /** @use HasFactory<MandatoryRestDayFactory> */
    use HasFactory;

    public const TYPES = ['legal_mandatory', 'electoral', 'company_internal'];

    public const SCOPES = ['national', 'subnational', 'company'];

    public const STATUSES = ['active', 'inactive'];

    public const CAPTURE_SOURCES = ['manual', 'seeder', 'import', 'system'];

    protected $fillable = [
        'name',
        'date',
        'type',
        'scope',
        'country_code',
        'jurisdiction_code',
        'source_reference',
        'capture_source',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
