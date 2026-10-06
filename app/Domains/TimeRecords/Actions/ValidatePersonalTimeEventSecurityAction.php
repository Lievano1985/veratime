<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\TimeRecords\Exceptions\PersonalTimeEventSecurityException;
use App\Domains\Workers\Actions\VerifyP256SignatureAction;
use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileMarkingTimeReference;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ValidatePersonalTimeEventSecurityAction
{
    public function __construct(
        private readonly ResolvePersonalMarkingSecurityAction $resolveSecurity,
        private readonly VerifyP256SignatureAction $verifySignature,
        private readonly BuildMobileMarkingPolicySnapshotAction $snapshot,
    ) {}

    /**
     * @param  array{event_type: string, occurred_at: string, timezone?: ?string, idempotency_key: string, security?: ?array}  $data
     * @return array<string, mixed>|null
     */
    public function handle(Company $company, User $user, Worker $worker, array $data): ?array
    {
        $now = CarbonImmutable::now('UTC');
        $policy = $this->resolveSecurity->resolveEffectivePolicy($company, $worker, $now);

        if (! $policy) {
            return null;
        }

        $security = $data['security'] ?? [];
        $reference = $this->resolveTimeReference($company, $user, $worker, $policy, (string) ($security['time_reference_id'] ?? ''), $now);
        $location = $this->validateLocation($policy, $security['location'] ?? null, (string) $data['occurred_at']);
        $binding = null;
        $signature = null;
        $payloadHash = null;

        if ($policy->requires_device_binding) {
            $binding = $this->resolveBinding($company, $user, $worker, (string) ($security['binding_id'] ?? ''));
            $signature = (string) ($security['signature'] ?? '');
            if ($signature === '') {
                throw new PersonalTimeEventSecurityException('biometric_key_invalid', 'Se requiere la firma del dispositivo autorizado.');
            }

            $payload = $this->signaturePayload($data, $policy, $reference, $binding, $location);
            try {
                $this->verifySignature->handle($binding->public_key_spki, $payload, $signature);
            } catch (InvalidArgumentException $exception) {
                throw new PersonalTimeEventSecurityException('biometric_key_invalid', $exception->getMessage());
            }
            $payloadHash = hash('sha256', $payload);
        }

        return [
            'policy' => $policy,
            'reference' => $reference,
            'binding' => $binding,
            'signature' => $signature,
            'payload_hash' => $payloadHash,
            'location' => $location,
            'policy_snapshot' => $this->snapshot->handle($policy),
        ];
    }

    private function resolveTimeReference(Company $company, User $user, Worker $worker, MobileMarkingPolicy $policy, string $referenceId, CarbonImmutable $now): MobileMarkingTimeReference
    {
        $reference = MobileMarkingTimeReference::query()
            ->where('public_id', $referenceId)
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('worker_id', $worker->id)
            ->where('mobile_marking_policy_id', $policy->id)
            ->where('policy_version', $policy->version)
            ->first();

        if (! $reference || $reference->expires_at->lessThanOrEqualTo($now)) {
            throw new PersonalTimeEventSecurityException('time_unverifiable', 'La referencia de tiempo de seguridad no es válida o venció. Conserva el marcaje pendiente para conciliación.', false);
        }

        return $reference;
    }

    private function resolveBinding(Company $company, User $user, Worker $worker, string $bindingId): MobileDeviceBinding
    {
        $binding = MobileDeviceBinding::query()
            ->where('public_id', $bindingId)
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('worker_id', $worker->id)
            ->where('status', MobileDeviceBinding::STATUS_ACTIVE)
            ->first();

        if (! $binding) {
            throw new PersonalTimeEventSecurityException('binding_inactive', 'El dispositivo no está autorizado para este marcaje.');
        }

        return $binding;
    }

    /** @return array{latitude: string, longitude: string, accuracy_meters: string, captured_at: CarbonImmutable, captured_at_raw: string, is_mocked: bool, distance_meters: ?float}|null */
    private function validateLocation(MobileMarkingPolicy $policy, mixed $location, string $occurredAt): ?array
    {
        $required = $policy->mode === MobileMarkingPolicy::MODE_CIRCLE
            || $policy->max_accuracy_meters !== null
            || $policy->max_location_age_seconds !== null;

        if (! $required && ! is_array($location)) {
            return null;
        }

        if (! is_array($location) || ! isset($location['latitude'], $location['longitude'], $location['accuracy_meters'], $location['captured_at'])) {
            throw new PersonalTimeEventSecurityException('location_unverifiable', 'La ubicación verificable es requerida para este marcaje.');
        }

        $capturedAt = CarbonImmutable::parse((string) $location['captured_at'])->utc();
        $occurredAtUtc = CarbonImmutable::parse($occurredAt)->utc();
        $isMocked = (bool) ($location['is_mocked'] ?? false);

        if ($isMocked) {
            throw new PersonalTimeEventSecurityException('location_unverifiable', 'La ubicación no puede verificarse desde una fuente simulada.');
        }

        $accuracy = (float) $location['accuracy_meters'];
        if ($policy->max_accuracy_meters !== null && $accuracy > $policy->max_accuracy_meters) {
            throw new PersonalTimeEventSecurityException('location_unverifiable', 'La precisión de ubicación no cumple la política de marcaje.');
        }

        if ($policy->max_location_age_seconds !== null && abs($capturedAt->diffInSeconds($occurredAtUtc, false)) > $policy->max_location_age_seconds) {
            throw new PersonalTimeEventSecurityException('location_unverifiable', 'La ubicación ya no es suficientemente reciente para este marcaje.');
        }

        $distance = null;
        if ($policy->mode === MobileMarkingPolicy::MODE_CIRCLE) {
            $distance = $this->distanceMeters((float) $policy->center_latitude, (float) $policy->center_longitude, (float) $location['latitude'], (float) $location['longitude']);
            if ($distance > $policy->radius_meters) {
                throw new PersonalTimeEventSecurityException('outside_area', 'La ubicación está fuera del área autorizada para marcar.');
            }
        }

        return [
            'latitude' => (string) $location['latitude'],
            'longitude' => (string) $location['longitude'],
            'accuracy_meters' => (string) $location['accuracy_meters'],
            'captured_at' => $capturedAt,
            'captured_at_raw' => (string) $location['captured_at'],
            'is_mocked' => $isMocked,
            'distance_meters' => $distance,
        ];
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $location */
    private function signaturePayload(array $data, MobileMarkingPolicy $policy, MobileMarkingTimeReference $reference, MobileDeviceBinding $binding, ?array $location): string
    {
        return implode("\n", [
            'VERA-MOBILE-EVENT-V1', (string) $data['idempotency_key'], (string) $data['event_type'], (string) $data['occurred_at'], (string) ($data['timezone'] ?? ''),
            $binding->public_id, $policy->public_id, (string) $policy->version, $reference->public_id,
            (string) ($location['latitude'] ?? ''), (string) ($location['longitude'] ?? ''), (string) ($location['accuracy_meters'] ?? ''),
            (string) ($location['captured_at_raw'] ?? ''), $location ? ($location['is_mocked'] ? '1' : '0') : '',
        ]);
    }

    private function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }
}
