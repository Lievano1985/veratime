<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileDeviceBindingAuthorization;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompleteMobileDeviceBindingAction
{
    public function handle(Company $company, User $user, Worker $worker, string $authorizationId, string $publicKeySpki, string $signature): MobileDeviceBinding
    {
        return DB::transaction(function () use ($company, $user, $worker, $authorizationId, $publicKeySpki, $signature): MobileDeviceBinding {
            $authorization = MobileDeviceBindingAuthorization::query()->where('public_id', $authorizationId)->lockForUpdate()->first();
            if (! $authorization || $authorization->company_id !== $company->id || $authorization->user_id !== $user->id || $authorization->worker_id !== $worker->id || $authorization->status !== MobileDeviceBindingAuthorization::STATUS_CHALLENGED || $authorization->challenge_expires_at?->isPast()) {
                throw new InvalidArgumentException('El desafío de vinculación no es válido o venció.');
            }

            $spki = $this->base64UrlDecode($publicKeySpki);
            $signatureBytes = $this->base64UrlDecode($signature);
            $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
            $key = openssl_pkey_get_public($pem);
            $details = $key ? openssl_pkey_get_details($key) : false;
            if (! $details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['ec']['curve_name'] ?? null) !== 'prime256v1') {
                throw new InvalidArgumentException('La clave pública debe usar P-256.');
            }

            $challenge = Crypt::decryptString((string) $authorization->challenge_encrypted);
            if (! hash_equals((string) $authorization->challenge_hash, hash('sha256', $challenge))) {
                throw new InvalidArgumentException('El desafío de vinculación no es válido.');
            }

            $payload = implode("\n", ['VERA-MOBILE-BINDING-V1', $authorization->public_id, $challenge, (string) $company->id, (string) $user->id, (string) $worker->id]);
            if (openssl_verify($payload, $signatureBytes, $key, OPENSSL_ALGO_SHA256) !== 1) {
                throw new InvalidArgumentException('La firma del desafío no es válida.');
            }

            $fingerprint = hash('sha256', $spki);
            if (MobileDeviceBinding::query()->where('company_id', $company->id)->where('key_fingerprint', $fingerprint)->exists()) {
                throw new InvalidArgumentException('Esta clave pública ya fue vinculada.');
            }

            $binding = MobileDeviceBinding::query()->create([
                'company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'mobile_device_binding_authorization_id' => $authorization->id,
                'device_name' => $authorization->requested_device_name, 'algorithm' => 'ES256', 'public_key_spki' => $publicKeySpki, 'key_fingerprint' => $fingerprint,
                'status' => MobileDeviceBinding::STATUS_ACTIVE, 'activated_at' => now(),
            ]);
            $authorization->forceFill(['status' => MobileDeviceBindingAuthorization::STATUS_CONSUMED, 'consumed_at' => now(), 'challenge_encrypted' => null])->save();

            return $binding;
        });
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value.str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true);
        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('La evidencia criptográfica no es válida.');
        }

        return $decoded;
    }
}
