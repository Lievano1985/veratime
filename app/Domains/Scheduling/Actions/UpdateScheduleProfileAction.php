<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateScheduleProfileAction
{
    public function __construct(private ReplaceScheduleProfileWeeklyRulesAction $replaceWeeklyRules)
    {
    }

    public function handle(Company $company, ScheduleProfile $profile, array $data, ?array $weeklyRules = null): ScheduleProfile
    {
        $this->assertTenant($company, $profile);

        $code = $this->normalizeCode($data['code'] ?? $profile->code);
        $profileType = $data['profile_type'] ?? $profile->profile_type;
        $patternMode = $this->normalizePatternMode($profileType, $data['pattern_mode'] ?? $profile->pattern_mode);
        if ($profileType !== $profile->profile_type) {
            throw new InvalidArgumentException('El tipo del perfil no se cambia en D1.');
        }
        if ($patternMode !== $profile->pattern_mode) {
            throw new InvalidArgumentException('La modalidad del perfil no se cambia en D1.');
        }

        $duplicate = ScheduleProfile::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->whereKeyNot($profile->id)
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Ya existe un perfil con el mismo codigo en esta empresa.');
        }

        return DB::transaction(function () use ($company, $profile, $data, $weeklyRules, $code, $patternMode): ScheduleProfile {
            $lockedProfile = ScheduleProfile::query()
                ->where('company_id', $company->id)
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProfile->fill([
                'code' => $code,
                'name' => $this->requiredString($data['name'] ?? $lockedProfile->name, 'El nombre del perfil es requerido.'),
                'description' => blank($data['description'] ?? null) ? null : trim((string) $data['description']),
                'pattern_mode' => $patternMode,
                'status' => in_array($data['status'] ?? $lockedProfile->status, ['active', 'inactive'], true)
                    ? ($data['status'] ?? $lockedProfile->status)
                    : $lockedProfile->status,
                'metadata' => $data['metadata'] ?? $lockedProfile->metadata ?? [],
            ]);
            $lockedProfile->save();

            if ($weeklyRules !== null) {
                $this->replaceWeeklyRules->handle($company, $lockedProfile, $weeklyRules);
            }

            return $lockedProfile->refresh()->load('weeklyRules.shiftTemplate');
        });
    }

    private function assertTenant(Company $company, ScheduleProfile $profile): void
    {
        if ($company->status !== 'active' || $profile->company_id !== $company->id) {
            throw new InvalidArgumentException('El perfil no pertenece a la empresa activa.');
        }
    }

    private function normalizeCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '' || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{1,49}$/', $code)) {
            throw new InvalidArgumentException('El codigo del perfil no es valido.');
        }

        return $code;
    }

    private function normalizePatternMode(string $profileType, ?string $patternMode): ?string
    {
        if ($profileType === 'pattern') {
            if ($patternMode !== 'weekly') {
                throw new InvalidArgumentException('Un perfil por patron requiere modalidad semanal en este bloque.');
            }

            return 'weekly';
        }

        if (filled($patternMode)) {
            throw new InvalidArgumentException('Solo los perfiles por patron admiten modalidad de patron.');
        }

        return null;
    }

    private function requiredString(?string $value, string $message): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
