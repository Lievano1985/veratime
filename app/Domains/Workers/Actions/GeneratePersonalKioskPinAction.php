<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class GeneratePersonalKioskPinAction
{
    public function handle(User $user, Company $company, string $password): array
    {
        abort_unless($user->status === 'active' && $company->status === 'active' && $user->belongsToCompany($company), 403);
        $worker = app(ResolvePersonalWorkerAction::class)->handle($user, $company);
        $attemptKey = 'personal-kiosk-password:'.$company->id.':'.$user->id;
        $generationKey = 'personal-kiosk-generation:'.$company->id.':'.$user->id;
        if (RateLimiter::tooManyAttempts($attemptKey, 5) || RateLimiter::tooManyAttempts($generationKey, 5)) {
            throw ValidationException::withMessages(['currentPassword' => 'Has realizado demasiados intentos. Espera antes de volver a generar tu clave.']);
        }
        RateLimiter::hit($attemptKey, 900);
        if (! Hash::check($password, $user->fresh()->password)) {
            throw ValidationException::withMessages(['currentPassword' => 'La contraseña de tu cuenta no es correcta.']);
        }

        $result = DB::transaction(function () use ($user, $company, $worker): array {
            $worker = Worker::query()->where('company_id', $company->id)->whereKey($worker->id)->lockForUpdate()->firstOrFail();
            $linkedWorker = app(ResolvePersonalWorkerAction::class)->handle($user, $company);
            abort_unless($linkedWorker->id === $worker->id, 403);
            $credential = $worker->credential()->where('company_id', $company->id)->lockForUpdate()->first();
            if ($credential && ! in_array($credential->status, ['active', 'reset_required'], true)) {
                throw ValidationException::withMessages(['currentPassword' => 'Tu credencial de kiosco está bloqueada o inactiva. Solicita su revisión a RH.']);
            }
            $code = trim((string) $worker->employee_code);
            if ($code === '' || WorkerCredential::query()->where('company_id', $company->id)->where('access_code', $code)->where('worker_id', '!=', $worker->id)->exists()) {
                throw ValidationException::withMessages(['currentPassword' => 'Tu número de empleado necesita revisión antes de generar la clave. Contacta a RH.']);
            }
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $credential = app(CreateOrUpdateWorkerCredentialAction::class)->handle($company, $worker, [
                'access_code' => $code, 'temporal_pin' => $pin, 'status' => 'active',
            ]);
            $credential->forceFill(['failed_attempts' => 0])->save();

            return ['code' => $code, 'pin' => $pin];
        });
        RateLimiter::clear($attemptKey);
        RateLimiter::hit($generationKey, 3600);
        Log::info('Personal kiosk credential regenerated.', ['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id]);

        return $result;
    }
}
