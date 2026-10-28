<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLayout extends Model
{
    protected $fillable = ['name', 'application', 'status', 'configuration'];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'is_default' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
