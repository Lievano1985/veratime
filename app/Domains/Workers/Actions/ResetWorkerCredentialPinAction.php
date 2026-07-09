<?php

namespace App\Domains\Workers\Actions;

use App\Models\WorkerCredential;
use Illuminate\Support\Facades\Hash;

class ResetWorkerCredentialPinAction
{
    public function handle(WorkerCredential $credential, string $temporalPin): WorkerCredential
    {
        $credential->forceFill([
            'pin_hash' => Hash::make($temporalPin),
            'status' => 'reset_required',
            'failed_attempts' => 0,
            'last_changed_at' => now(),
        ])->save();

        return $credential->refresh();
    }
}
