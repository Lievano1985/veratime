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

        if (ScheduleProfile::query()->where('company_id', $company->id)->where('code', $code)->exists()) {
            throw new InvalidArgumentException('Ya existe un perfil con el mismo codigo en esta empresa.');
        }

        if ($profileType === 'fixed' && $weeklyRules === []) {
            throw new InvalidArgumentException('Un perfil fijo requiere siete reglas semanales.');
        }

        if ($profileType === 'variable' && $weeklyRules !== []) {
            throw new InvalidArgumentException('Un perfil variable no admite reglas semanales.');
        }

        return DB::transaction(function () use ($company, $data, $weeklyRules, $code, $profileType): ScheduleProfile {
            $profile = new ScheduleProfile([
                'code' => $code,
                'name' => $this->requiredString($data['name'] ?? null, 'El nombre del perfil es requerido.'),
                'description' => blank($data['description'] ?? null) ? null : trim((string) $data['description']),
                'profile_type' => $profileType,
                'status' => in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? ($data['status'] ?? 'active') : 'active',
                'metadata' => $data['metadata'] ?? [],
            ]);
            $profile->company()->associate($company);
            $profile->save();

            if ($profileType === 'fixed') {
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
        if (! in_array($type, ['fixed', 'variable'], true)) {
            throw new InvalidArgumentException('El tipo de perfil no es valido para D1.');
        }

        return $type;
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
