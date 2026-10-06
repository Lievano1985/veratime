<?php

namespace App\Domains\Workers\Actions;

use InvalidArgumentException;

class VerifyP256SignatureAction
{
    /** @return array{spki: string, fingerprint: string} */
    public function handle(string $publicKeySpki, string $payload, string $signature): array
    {
        $spki = $this->base64UrlDecode($publicKeySpki);
        $signatureBytes = $this->base64UrlDecode($signature);
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        $details = $key ? openssl_pkey_get_details($key) : false;

        if (! $details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['ec']['curve_name'] ?? null) !== 'prime256v1') {
            throw new InvalidArgumentException('La clave pública debe usar P-256.');
        }

        if (openssl_verify($payload, $signatureBytes, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new InvalidArgumentException('La firma criptográfica no es válida.');
        }

        return ['spki' => $spki, 'fingerprint' => hash('sha256', $spki)];
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
