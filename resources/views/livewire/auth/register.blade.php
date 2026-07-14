<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public function register(): void
    {
        abort(403, 'El registro publico esta deshabilitado en Vera Time.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Registro deshabilitado" description="El acceso de usuarios lo crea una persona administradora despues de asignar una empresa activa." />

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        <x-text-link href="{{ route('login') }}">Iniciar sesion</x-text-link>
    </div>
</div>
