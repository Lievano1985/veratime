<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetLocalSuperAdministratorPasswordCommand extends Command
{
    protected $signature = 'veratime:reset-local-super-admin-password';

    protected $description = 'Restablece la contraseña de un superadministrador en el entorno local';

    public function handle(): int
    {
        if (! app()->environment('local') || ! $this->input->isInteractive()) {
            $this->error('Ejecuta el comando en una terminal interactiva del entorno local.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->ask('Correo del superadministrador')));
        $user = User::query()->where('email', $email)->where('global_role', RoleKey::SUPER_ADMIN)->first();
        if (! $user?->isSuperAdmin()) {
            $this->error('No existe un superadministrador activo con ese correo.');

            return self::FAILURE;
        }

        $this->line('Usa al menos 12 caracteres, con mayúsculas, minúsculas y números.');
        $validator = Validator::make([
            'password' => $this->secret('Nueva contraseña', false),
            'password_confirmation' => $this->secret('Confirma la contraseña', false),
        ], [
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => $validator->validated()['password'], 'remember_token' => Str::random(60)])->save();
        $this->info('Contraseña del superadministrador local actualizada.');

        return self::SUCCESS;
    }
}
