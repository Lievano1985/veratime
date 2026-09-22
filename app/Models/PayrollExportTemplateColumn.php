<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollExportTemplateColumn extends Model
{
    protected $fillable = [
        'source_key',
        'header',
        'position',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PayrollExportTemplate::class, 'payroll_export_template_id');
    }
}
