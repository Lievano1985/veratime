<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    public const STATUS_NEW = 'new';

    protected $fillable = [
        'contact_name',
        'company_name',
        'email',
        'phone',
        'team_size',
        'message',
        'status',
        'consented_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }
}
