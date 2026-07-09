<?php

namespace App\Domains\Workers\Actions;

use App\Models\WorkerCredential;

class BlockWorkerCredentialAction
{
    public function handle(WorkerCredential $credential): WorkerCredential
    {
        $credential->forceFill([
            'status' => 'blocked',
        ])->save();

        return $credential->refresh();
    }
}
