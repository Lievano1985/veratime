<?php

namespace App\Domains\Marketing\Actions;

use App\Domains\Integrations\Exceptions\BrevoDeliveryException;
use App\Models\DemoRequest;
use Illuminate\Support\Facades\Log;

class CreateDemoRequestAction
{
    public function __construct(private readonly SendDemoRequestNotificationWithBrevoAction $notify) {}

    /**
     * @param  array{contact_name: string, company_name?: string|null, email: string, phone: string, team_size?: int|null, message?: string|null}  $attributes
     */
    public function handle(array $attributes): DemoRequest
    {
        $demoRequest = DemoRequest::query()->create([
            ...$attributes,
            'status' => DemoRequest::STATUS_NEW,
            'consented_at' => now(),
            'source' => 'welcome',
        ]);

        try {
            $this->notify->handle($demoRequest);
        } catch (BrevoDeliveryException $exception) {
            Log::error('Demo request notification delivery failed.', [
                'status_code' => $exception->statusCode,
            ]);
        }

        return $demoRequest;
    }
}
