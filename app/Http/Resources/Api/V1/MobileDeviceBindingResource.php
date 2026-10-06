<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MobileDeviceBinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MobileDeviceBinding */
class MobileDeviceBindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'device_name' => $this->device_name,
            'algorithm' => $this->algorithm,
            'status' => $this->status,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
        ];
    }
}
