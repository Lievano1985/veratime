<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertType extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const SEVERITY_INFORMATIONAL = 'informational';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'code',
        'name',
        'description',
        'default_severity',
        'category',
        'status',
    ];

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
