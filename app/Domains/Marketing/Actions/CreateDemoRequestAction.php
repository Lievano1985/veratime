<?php

namespace App\Domains\Marketing\Actions;

use App\Models\DemoRequest;

class CreateDemoRequestAction
{
    /**
     * @param  array{contact_name: string, company_name?: string|null, email: string, phone: string, team_size?: int|null, message?: string|null}  $attributes
     */
    public function handle(array $attributes): DemoRequest
    {
        return DemoRequest::query()->create([
            ...$attributes,
            'status' => DemoRequest::STATUS_NEW,
            'consented_at' => now(),
            'source' => 'welcome',
        ]);
    }
}
