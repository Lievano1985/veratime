<?php

namespace App\Models;

use Database\Factories\LegalRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalRule extends Model
{
    /** @use HasFactory<LegalRuleFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'status',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(LegalRuleVersion::class);
    }
}
