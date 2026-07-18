<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateScheduleProfileAction
{
    public function __construct(private ReplaceScheduleProfileWeeklyRulesAction $replaceWeeklyRules)
    {
    }

    public function handle(Company $company, array $data, array $weeklyRules = []): ScheduleProfile
    {
        $this->assertActiveCompany($company);

        $code = $this->normalizeCode($data['code'] ?? null);
        $profileType = $this->normalizeProfileType($data['profile_type'] ?? null);
        $patternMode = $this->normalizePatternMode($profileType, $data['pattern_mode'] ?? null);

        if (ScheduleProfile::query()->where('company_id', $company->id)->where('code', $code)->exists()) {
            throw new InvalidArgumentException('Ya existe un perfil con el mismo codigo en esta empresa.');
        }

        if ($profileType === 'pattern' && $patternMode === 'weekly' && $weeklyRules === []) {
            throw new InvalidArgumentException('Un perfil por patron semanal requiere siete reglas semanales.');
        }

        if ($profileType === 'calendar' && $weeklyRules !== []) {
            throw new InvalidArgumentException('Un perfil por calendario no admite reglas semanales.');
        }

        return DB::transaction(function () use ($company, $data, $weeklyRules, $code, $profileType, $patternMode): ScheduleProfile {
            $profile = new ScheduleProfile([
                'code' => $code,
                'name' => $this->requiredString($data['name'] ?? null, 'El nombre del perfil es requerido.'),
                'description' => blank($data['description'] ?? null) ? null : trim((string) $data['description']),
                'profile_type' => $profileType,
                'pattern_mode' => $patternMode,
                'status' => in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? ($data['status'] ?? 'active') : 'active',
                'metadata' => $data['metadata'] ?? [],
            ]);
            $profile->company()->associate($company);
            $profile->save();

            if ($profileType === 'pattern' && $patternMode === 'weekly') {
                $this->replaceWeeklyRules->handle($company, $profile, $weeklyRules);
            }

            return $profile->refresh()->load('weeklyRules.shiftTemplate');
        });
    }

    private function assertActiveCompany(Company $company): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('El perfil requiere una empresa activa.');
        }
    }

    private function normalizeProfileType(?string $type): string
    {
        if (! in_array($type, ['pattern', 'calendar'], true)) {
            throw new InvalidArgumentException('El tipo de perfil no es valido para D1.');
        }

        return $type;
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

    private function normalizeCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '' || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{1,49}$/', $code)) {
            throw new InvalidArgumentException('El codigo del perfil no es valido.');
        }

        return $code;
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
