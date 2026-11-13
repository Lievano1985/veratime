<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\AttendanceClock;
use App\Models\Company;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ConfigureAttendanceClockAction
{
    public function register(Company $company, array $data): AttendanceClock
    {
        $center = $company->centers()->where('status', 'active')->find($data['centerId']);
        if (! $center) {
            throw ValidationException::withMessages(['centerId' => 'Selecciona un centro activo de esta empresa.']);
        }

        try {
            return AttendanceClock::create([
                'company_id' => $company->id,
                'center_id' => $center->id,
                'name' => trim($data['clockName']),
                'serial' => strtoupper(trim($data['serial'])),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['serial' => 'Este número de serie ya está registrado.']);
        }
    }

    public function link(Company $company, array $data): void
    {
        $clock = AttendanceClock::query()->where('company_id', $company->id)->find($data['clockId']);
        if (! $clock) {
            throw ValidationException::withMessages(['clockId' => 'Selecciona un reloj de esta empresa.']);
        }

        $worker = $company->workers()->where('status', 'active')
            ->where('employee_code', trim($data['employeeCode']))->first();
        if (! $worker) {
            throw ValidationException::withMessages(['employeeCode' => 'No se encontró un trabajador activo con ese código en esta empresa.']);
        }

        try {
            $clock->workers()->attach($worker->id, ['clock_user_id' => $data['clockUserId']]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['clockUserId' => 'Este ID ya tiene un vínculo en el reloj.']);
        }
    }
}
