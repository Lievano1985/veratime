<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\TimeRecords\Exceptions\PersonalTimeEventConflictException;
use App\Domains\Workers\Actions\VerifyP256SignatureAction;
use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileOfflineMarkingAuthorization;
use App\Models\MobileOfflineMarkingCapture;
use App\Models\PersonalTimeEventSubmission;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReconcileOfflinePersonalTimeEventAction
{
    public function __construct(
        private readonly CreateTimeEventAction $createTimeEvent,
        private readonly HashPersonalTimeEventPayloadAction $hashPayload,
        private readonly VerifyP256SignatureAction $verifySignature,
    ) {}

    /**
     * Conserva siempre la captura recibida. Sólo crea un evento válido si la evidencia
     * permite verificar que fue tomada dentro de una autorización offline vigente.
     *
     * @param  array<string, mixed>  $data
     * @return array{capture: MobileOfflineMarkingCapture, event: ?TimeEvent, created: bool}
     */
    public function handle(Company $company, User $user, Worker $worker, array $data): array
    {
        $payloadHash = $this->hashPayload->handle($data);

        return DB::transaction(function () use ($company, $user, $worker, $data, $payloadHash): array {
            $existing = MobileOfflineMarkingCapture::query()
                ->where('company_id', $company->id)
                ->where('client_event_id', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->user_id !== $user->id || $existing->worker_id !== $worker->id || ! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new PersonalTimeEventConflictException;
                }

                return ['capture' => $existing, 'event' => $existing->event, 'created' => false];
            }

            return $this->reconcileNewCapture($company, $user, $worker, $data, $payloadHash);
        });
    }

    /** @param array<string, mixed> $data @return array{capture: MobileOfflineMarkingCapture, event: ?TimeEvent, created: bool} */
    private function reconcileNewCapture(Company $company, User $user, Worker $worker, array $data, string $payloadHash): array
    {
        $security = is_array($data['security'] ?? null) ? $data['security'] : [];
        $location = is_array($security['location'] ?? null) ? $security['location'] : null;
        $authorizationId = (string) ($security['offline_authorization_id'] ?? '');
        $bindingId = (string) ($security['binding_id'] ?? '');
        $policyId = (string) ($security['policy_id'] ?? '');
        $policyVersion = isset($security['policy_version']) ? (int) $security['policy_version'] : null;
        $signature = (string) ($security['signature'] ?? '');
        $monotonic = isset($security['monotonic_elapsed_milliseconds']) ? (int) $security['monotonic_elapsed_milliseconds'] : null;
        $claimedAt = $this->parseTimestamp((string) $data['occurred_at']);
        $locationCapturedAt = $location && isset($location['captured_at']) ? $this->parseTimestamp((string) $location['captured_at']) : null;

        $authorization = MobileOfflineMarkingAuthorization::query()
            ->where('public_id', $authorizationId)
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('worker_id', $worker->id)
            ->lockForUpdate()
            ->first();
        $binding = $authorization?->binding;
        $policy = $authorization?->policy;
        $estimatedAt = $authorization && $monotonic !== null
            ? $authorization->issued_at->addMilliseconds($monotonic - $authorization->issued_monotonic_milliseconds)
            : null;

        $reason = $this->verificationReason($authorization, $binding, $policy, $bindingId, $policyId, $policyVersion, $monotonic, $estimatedAt, $claimedAt, $signature, $data, $location);
        $locationResult = $authorization ? $this->validateLocation($authorization->policy_snapshot, $location, $claimedAt) : ['reason' => null, 'location' => null];
        $reason ??= $locationResult['reason'];

        $capture = MobileOfflineMarkingCapture::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'worker_id' => $worker->id,
            'mobile_offline_marking_authorization_id' => $authorization?->id,
            'mobile_marking_policy_id' => $policy?->id,
            'mobile_device_binding_id' => $binding?->id,
            'client_event_id' => $data['idempotency_key'],
            'event_type' => $data['event_type'],
            'timezone' => $data['timezone'] ?? null,
            'offline_authorization_public_id' => $authorizationId ?: null,
            'binding_public_id' => $bindingId ?: null,
            'policy_public_id' => $policyId ?: null,
            'policy_version' => $policyVersion,
            'occurred_at_claimed' => $claimedAt,
            'occurred_at_raw' => (string) $data['occurred_at'],
            'monotonic_elapsed_milliseconds' => $monotonic,
            'estimated_occurred_at' => $estimatedAt,
            'status' => MobileOfflineMarkingCapture::STATUS_PENDING_REVIEW,
            'review_reason' => $reason,
            'signature' => $signature ?: null,
            'payload_hash' => $payloadHash,
            'policy_snapshot' => $authorization?->policy_snapshot,
            'location_latitude' => $location['latitude'] ?? null,
            'location_longitude' => $location['longitude'] ?? null,
            'location_accuracy_meters' => $location['accuracy_meters'] ?? null,
            'location_captured_at' => $locationCapturedAt,
            'location_captured_at_raw' => $location['captured_at'] ?? null,
            'location_is_mocked' => array_key_exists('is_mocked', $location ?? []) ? (bool) $location['is_mocked'] : null,
            'distance_meters' => $locationResult['location']['distance_meters'] ?? null,
        ]);

        if ($reason !== null || ! $authorization || ! $binding || ! $policy || ! $estimatedAt) {
            return ['capture' => $capture, 'event' => null, 'created' => true];
        }

        if ($this->hasAssistedMarkingAtTheSameMoment($company, $worker, $data['event_type'], $claimedAt)) {
            $capture->update(['review_reason' => 'assisted_marking_exists']);

            return ['capture' => $capture->refresh(), 'event' => null, 'created' => true];
        }

        $relationship = $worker->activeEmploymentRelationship()->where('company_id', $company->id)->with('center')->first();
        $event = $this->createTimeEvent->handle($company, $worker, [
            'event_type' => $data['event_type'],
            'occurred_at_utc' => $data['occurred_at'],
            'timezone' => $data['timezone'] ?? null,
            'source' => 'pwa',
            'status' => 'valid',
            'idempotency_key' => $data['idempotency_key'],
            'metadata' => [
                'channel' => 'pwa_personal_offline',
                'token_id' => $user->currentAccessToken()?->getKey(),
                'trace_id' => $data['trace_id'] ?? null,
                'device' => $data['device'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ],
        ], $relationship, $relationship?->center, $user);

        PersonalTimeEventSubmission::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'worker_id' => $worker->id,
            'time_event_id' => $event->id,
            'client_event_id' => $data['idempotency_key'],
            'payload_hash' => $payloadHash,
        ]);

        $capture->update(['time_event_id' => $event->id, 'status' => MobileOfflineMarkingCapture::STATUS_ACCEPTED, 'review_reason' => null]);

        return ['capture' => $capture->refresh(), 'event' => $event, 'created' => true];
    }

    /** @param array<string, mixed>|null $location */
    private function verificationReason(?MobileOfflineMarkingAuthorization $authorization, ?MobileDeviceBinding $binding, mixed $policy, string $bindingId, string $policyId, ?int $policyVersion, ?int $monotonic, ?CarbonImmutable $estimatedAt, ?CarbonImmutable $claimedAt, string $signature, array $data, ?array $location): ?string
    {
        if (! $authorization) {
            return 'offline_authorization_unknown';
        }
        if ($monotonic === null || $monotonic < $authorization->issued_monotonic_milliseconds) {
            return 'monotonic_restarted';
        }
        if (! $estimatedAt || $estimatedAt->greaterThan($authorization->expires_at)) {
            return 'offline_authorization_expired_at_capture';
        }
        if ($authorization->revoked_at && $authorization->revoked_at->lessThanOrEqualTo($estimatedAt)) {
            return 'offline_authorization_revoked_at_capture';
        }
        if (! $this->policySnapshotIsEffectiveAt($authorization->policy_snapshot, $estimatedAt)) {
            return 'policy_not_effective_at_capture';
        }
        if (! $binding || $binding->public_id !== $bindingId) {
            return 'binding_mismatch';
        }
        if ($binding->revoked_at && $binding->revoked_at->lessThanOrEqualTo($estimatedAt)) {
            return 'binding_revoked_at_capture';
        }
        if (! $policy || $policy->public_id !== $policyId || $authorization->policy_version !== $policyVersion) {
            return 'policy_mismatch';
        }
        if (! $claimedAt || abs($estimatedAt->diffInSeconds($claimedAt, false)) > $authorization->max_clock_drift_seconds) {
            return 'clock_drift_exceeded';
        }
        if ($signature === '') {
            return 'signature_missing';
        }

        try {
            $this->verifySignature->handle($binding->public_key_spki, $this->signaturePayload($data, $authorization, $bindingId, $policyId, $policyVersion, $monotonic, $location), $signature);
        } catch (InvalidArgumentException) {
            return 'signature_invalid';
        }

        return null;
    }

    /** @param array<string, mixed> $snapshot @param array<string, mixed>|null $location @return array{reason: ?string, location: array<string, mixed>|null} */
    private function validateLocation(array $snapshot, ?array $location, ?CarbonImmutable $occurredAt): array
    {
        $required = ($snapshot['mode'] ?? null) === 'circle' || ($snapshot['max_accuracy_meters'] ?? null) !== null || ($snapshot['max_location_age_seconds'] ?? null) !== null;
        if (! $required && ! $location) {
            return ['reason' => null, 'location' => null];
        }
        if (! $location || ! isset($location['latitude'], $location['longitude'], $location['accuracy_meters'], $location['captured_at']) || ! $occurredAt) {
            return ['reason' => 'location_unverifiable', 'location' => null];
        }
        $capturedAt = $this->parseTimestamp((string) $location['captured_at']);
        if (! $capturedAt || (bool) ($location['is_mocked'] ?? false)) {
            return ['reason' => 'location_unverifiable', 'location' => null];
        }
        if (($snapshot['max_accuracy_meters'] ?? null) !== null && (float) $location['accuracy_meters'] > (float) $snapshot['max_accuracy_meters']) {
            return ['reason' => 'location_unverifiable', 'location' => null];
        }
        if (($snapshot['max_location_age_seconds'] ?? null) !== null && abs($capturedAt->diffInSeconds($occurredAt, false)) > (int) $snapshot['max_location_age_seconds']) {
            return ['reason' => 'location_unverifiable', 'location' => null];
        }
        $distance = null;
        if (($snapshot['mode'] ?? null) === 'circle') {
            $distance = $this->distanceMeters((float) $snapshot['center_latitude'], (float) $snapshot['center_longitude'], (float) $location['latitude'], (float) $location['longitude']);
            if ($distance > (float) $snapshot['radius_meters']) {
                return ['reason' => 'outside_area', 'location' => ['distance_meters' => $distance]];
            }
        }

        return ['reason' => null, 'location' => ['distance_meters' => $distance]];
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $location */
    private function signaturePayload(array $data, MobileOfflineMarkingAuthorization $authorization, string $bindingId, string $policyId, ?int $policyVersion, int $monotonic, ?array $location): string
    {
        return implode("\n", [
            'VERA-MOBILE-OFFLINE-EVENT-V1', (string) $data['idempotency_key'], (string) $data['event_type'], (string) $data['occurred_at'], (string) ($data['timezone'] ?? ''),
            $bindingId, $policyId, (string) $policyVersion, $authorization->public_id, (string) $monotonic,
            (string) ($location['latitude'] ?? ''), (string) ($location['longitude'] ?? ''), (string) ($location['accuracy_meters'] ?? ''),
            (string) ($location['captured_at'] ?? ''), $location ? ((bool) ($location['is_mocked'] ?? false) ? '1' : '0') : '',
        ]);
    }

    private function hasAssistedMarkingAtTheSameMoment(Company $company, Worker $worker, string $eventType, CarbonImmutable $claimedAt): bool
    {
        return TimeEvent::query()->where('company_id', $company->id)->where('worker_id', $worker->id)->where('source', 'admin_manual')->where('event_type', $eventType)->where('occurred_at_utc', $claimedAt)->exists();
    }

    private function parseTimestamp(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function policySnapshotIsEffectiveAt(array $snapshot, CarbonImmutable $capturedAt): bool
    {
        $validFrom = isset($snapshot['valid_from']) && $snapshot['valid_from'] !== null ? $this->parseTimestamp((string) $snapshot['valid_from']) : null;
        $validUntil = isset($snapshot['valid_until']) && $snapshot['valid_until'] !== null ? $this->parseTimestamp((string) $snapshot['valid_until']) : null;

        return (! $validFrom || $validFrom->lessThanOrEqualTo($capturedAt))
            && (! $validUntil || $validUntil->greaterThanOrEqualTo($capturedAt));
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
