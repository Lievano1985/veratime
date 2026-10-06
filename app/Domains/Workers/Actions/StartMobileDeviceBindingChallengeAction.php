<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\MobileDeviceBindingAuthorization;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StartMobileDeviceBindingChallengeAction
{
    /** @return array{authorization: MobileDeviceBindingAuthorization, challenge: string, payload: string} */
    public function handle(Company $company, User $user, Worker $worker, string $authorizationCode, string $deviceName): array
    {
        $now = now();

        return DB::transaction(function () use ($company, $user, $worker, $authorizationCode, $deviceName, $now): array {
            $authorization = MobileDeviceBindingAuthorization::query()
                ->where('authorization_secret_hash', hash('sha256', $authorizationCode))
                ->lockForUpdate()->first();

            if (! $authorization || $authorization->company_id !== $company->id || $authorization->user_id !== $user->id || $authorization->worker_id !== $worker->id) {
                throw new InvalidArgumentException('El código de vinculación no es válido.');
            }

            if ($authorization->expires_at->lessThanOrEqualTo($now)) {
                $authorization->forceFill(['status' => MobileDeviceBindingAuthorization::STATUS_EXPIRED])->save();
                throw new InvalidArgumentException('El código de vinculación venció.');
            }

            if ($authorization->status !== MobileDeviceBindingAuthorization::STATUS_PENDING) {
                throw new InvalidArgumentException('El código de vinculación ya no está disponible.');
            }

            $challenge = Str::random(64);
            $challengeExpiresAt = $now->copy()->addMinutes(5);
            $authorization->forceFill([
                'status' => MobileDeviceBindingAuthorization::STATUS_CHALLENGED,
                'requested_device_name' => trim($deviceName),
                'challenge_hash' => hash('sha256', $challenge),
                'challenge_encrypted' => Crypt::encryptString($challenge),
                'challenge_issued_at' => $now,
                'challenge_expires_at' => $challengeExpiresAt,
            ])->save();

            $payload = implode("\n", [
                'VERA-MOBILE-BINDING-V1', $authorization->public_id, $challenge,
                (string) $company->id, (string) $user->id, (string) $worker->id,
            ]);

            return ['authorization' => $authorization, 'challenge' => $challenge, 'payload' => $payload];
        });
    }
}
