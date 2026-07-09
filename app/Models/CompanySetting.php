<?php

namespace App\Models;

use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'payroll_period_type',
        'default_timezone',
        'default_closure_day',
        'allow_worker_corrections',
        'require_pin_for_kiosk',
        'require_pin_for_confirmation',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'default_closure_day' => 'integer',
            'allow_worker_corrections' => 'boolean',
            'require_pin_for_kiosk' => 'boolean',
            'require_pin_for_confirmation' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
