<?php

namespace App\Domains\Companies\Actions;

use App\Models\Center;

class InactivateCenterAction
{
    public function handle(Center $center): Center
    {
        $center->forceFill([
            'status' => 'inactive',
        ])->save();

        return $center->refresh();
    }
}
