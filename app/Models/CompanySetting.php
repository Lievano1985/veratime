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
        'work_days_auto_refresh_time',
        'work_days_last_refreshed_at',
        'work_days_last_refresh_status',
        'work_days_last_refresh_summary',
        'allow_worker_corrections',
        'require_pin_for_kiosk',
        'require_pin_for_confirmation',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'default_closure_day' => 'integer',
            'work_days_last_refreshed_at' => 'immutable_datetime',
            'work_days_last_refresh_summary' => 'array',
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
