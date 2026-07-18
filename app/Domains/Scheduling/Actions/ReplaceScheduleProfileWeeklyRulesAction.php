<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplaceScheduleProfileWeeklyRulesAction
{
    public function handle(Company $company, ScheduleProfile $profile, array $rules): ScheduleProfile
    {
        $this->assertTenant($company, $profile);

        if ($profile->profile_type !== 'pattern' || $profile->pattern_mode !== 'weekly') {
            throw new InvalidArgumentException('Solo los perfiles por patron semanal admiten reglas semanales.');
        }

        $normalized = $this->normalizeRules($company, $rules);

        return DB::transaction(function () use ($company, $profile, $normalized): ScheduleProfile {
            $lockedProfile = ScheduleProfile::query()
                ->where('company_id', $company->id)
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProfile->weeklyRules()->lockForUpdate()->get();
            $lockedProfile->weeklyRules()->delete();

            foreach ($normalized as $rule) {
                $model = $lockedProfile->weeklyRules()->make($rule);
                $model->company()->associate($company);
                $model->save();
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

    private function normalizeRules(Company $company, array $rules): array
    {
        if (count($rules) !== 7) {
            throw new InvalidArgumentException('Un perfil por patron semanal requiere exactamente siete reglas semanales.');
        }

        $normalized = [];
        $seenDays = [];
        $hasShift = false;

        foreach ($rules as $rule) {
            $day = (int) ($rule['day_of_week'] ?? 0);
            if ($day < 1 || $day > 7 || in_array($day, $seenDays, true)) {
                throw new InvalidArgumentException('Las reglas semanales deben cubrir dias ISO 1 a 7 sin duplicados.');
            }
            $seenDays[] = $day;

            $dayType = $rule['day_type'] ?? null;
            if (! in_array($dayType, ['shift', 'rest'], true)) {
                throw new InvalidArgumentException('El tipo de dia semanal no es valido.');
            }

            $shiftTemplateId = $rule['shift_template_id'] ?? null;
            if ($dayType === 'shift') {
                if (! $shiftTemplateId) {
                    throw new InvalidArgumentException('Un dia con turno requiere plantilla activa.');
                }

                $template = ShiftTemplate::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'active')
                    ->whereKey($shiftTemplateId)
                    ->first();

                if (! $template) {
                    throw new InvalidArgumentException('La plantilla del dia no pertenece a la empresa activa o no esta activa.');
                }

                $hasShift = true;
            } elseif ($shiftTemplateId !== null) {
                throw new InvalidArgumentException('Un dia de descanso no debe tener plantilla.');
            }

            $normalized[] = [
                'day_of_week' => $day,
                'day_type' => $dayType,
                'shift_template_id' => $dayType === 'shift' ? $shiftTemplateId : null,
                'metadata' => $rule['metadata'] ?? [],
            ];
        }

        if (! $hasShift) {
            throw new InvalidArgumentException('Un perfil por patron semanal requiere al menos un dia con turno.');
        }

        usort($normalized, fn (array $a, array $b): int => $a['day_of_week'] <=> $b['day_of_week']);

        return $normalized;
    }
}
