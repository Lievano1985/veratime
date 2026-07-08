<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public function register(): void
    {
        abort(403, 'Public registration is disabled for Vera Time.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Registration disabled" description="User access is created by an administrator after assigning an active company." />

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        <x-text-link href="{{ route('login') }}">Log in</x-text-link>
    </div>
</div>
