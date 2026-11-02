<?php

namespace App\Console\Commands;

use App\Domains\Identity\Actions\CreateSuperAdministratorAction;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateSuperAdministratorCommand extends Command
{
    protected $signature = 'veratime:create-super-admin';

    protected $description = 'Crear un superadministrador mediante captura oculta de contraseña';

    public function handle(CreateSuperAdministratorAction $action): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta este comando en una terminal interactiva. No acepta contraseñas como argumentos.');

            return self::FAILURE;
        }
        $this->line('La contraseña requiere al menos 12 caracteres, mayúsculas, minúsculas y números.');
        $name = $this->ask('Nombre');
        $email = $this->ask('Correo electrónico');
        // Disable Symfony's visible-input fallback when hidden entry is unavailable.
        $password = $this->secret('Contraseña', false);
        $confirmation = $this->secret('Confirma la contraseña', false);
        try {
            $user = $action->handle(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation]);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        } finally {
            unset($password, $confirmation);
        }
        $this->info('Superadministrador creado: '.$user->email);
        $this->line('La contraseña no se muestra ni se guarda en texto plano.');

        return self::SUCCESS;
    }
}
