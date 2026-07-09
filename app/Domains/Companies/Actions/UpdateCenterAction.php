<?php

namespace App\Domains\Companies\Actions;

use App\Models\Center;

class UpdateCenterAction
{
    public function handle(Center $center, array $data): Center
    {
        $center->fill([
            'code' => $data['code'],
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'status' => $data['status'],
            'address' => $data['address'] ?? null,
            'metadata' => $data['metadata'] ?? $center->metadata ?? [],
        ]);

        $center->save();

        return $center->refresh();
    }
}
