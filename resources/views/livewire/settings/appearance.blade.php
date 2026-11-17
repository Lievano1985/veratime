<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout heading="Apariencia" subheading="Actualiza la apariencia de tu cuenta">
        @if (config('app.force_light_appearance'))
            <p class="text-sm text-surface-muted">Vera Time utiliza el tema claro.</p>
        @else
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">Claro</flux:radio>
                <flux:radio value="dark" icon="moon">Oscuro</flux:radio>
                <flux:radio value="system" icon="computer-desktop">Sistema</flux:radio>
            </flux:radio.group>
        @endif
    </x-settings.layout>
</div>
