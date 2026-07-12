<?php

use App\Domains\TimeRecords\Actions\RegisterKioskTimeEventAction;
use App\Domains\TimeRecords\Actions\ResolveCurrentTimeRecordStateAction;
use App\Domains\TimeRecords\Actions\ResolveKioskCredentialAction;
use App\Models\WorkerCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    private const TOKEN_TTL_MINUTES = 5;

    public string $accessCode = '';

    public string $pin = '';

    public ?string $credentialToken = null;

    public ?string $workerName = null;

    public ?string $localDate = null;

    public ?string $timezone = null;

    public array $allowedActions = [];

    public ?string $confirmationMessage = null;

    public ?string $confirmationTime = null;

    public function identify(ResolveKioskCredentialAction $resolveCredential, ResolveCurrentTimeRecordStateAction $resolveState): void
    {
        $this->validate([
            'accessCode' => ['required', 'string', 'max:50'],
            'pin' => ['required', 'string', 'max:20'],
        ]);

        try {
            $credential = $resolveCredential->handle($this->accessCode, $this->pin);
        } catch (\InvalidArgumentException) {
            $this->pin = '';

            throw ValidationException::withMessages([
                'accessCode' => 'No se pudo validar la credencial.',
            ]);
        }

        $state = $this->stateForCredential($credential, $resolveState);

        $this->credentialToken = Crypt::encryptString(json_encode([
            'credential_id' => $credential->id,
            'worker_id' => $credential->worker_id,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
        $this->workerName = $credential->worker->full_name;
        $this->localDate = $state['local_date'];
        $this->timezone = $state['timezone'];
        $this->allowedActions = $state['allowed_actions'];
        $this->confirmationMessage = null;
        $this->confirmationTime = null;
        $this->pin = '';
    }

    public function record(string $eventType, RegisterKioskTimeEventAction $register, ResolveCurrentTimeRecordStateAction $resolveState): void
    {
        $credential = $this->credentialFromToken();

        try {
            $event = $register->handle($credential, $eventType);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'accessCode' => $exception->getMessage(),
            ]);
        }

        $this->confirmationMessage = $this->eventMessage($event->event_type);
        $this->confirmationTime = $event->occurred_local_date->toDateString().' '.$event->occurred_local_time;
        Session::flash('status', $this->confirmationMessage);

        $this->resetKioskState(keepConfirmation: true);
    }

    public function resetKiosk(): void
    {
        $this->resetKioskState(keepConfirmation: false);
    }

    private function credentialFromToken(): WorkerCredential
    {
        if (! $this->credentialToken) {
            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        try {
            $payload = json_decode(Crypt::decryptString($this->credentialToken), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        $issuedAt = (int) ($payload['issued_at'] ?? 0);

        if ($issuedAt <= 0 || now()->timestamp - $issuedAt > self::TOKEN_TTL_MINUTES * 60) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        $credential = WorkerCredential::query()
            ->with(['company', 'worker'])
            ->find((int) ($payload['credential_id'] ?? 0));

        if (! $credential || $credential->worker_id !== (int) ($payload['worker_id'] ?? 0)) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        return $credential;
    }

    private function stateForCredential(WorkerCredential $credential, ResolveCurrentTimeRecordStateAction $resolveState): array
    {
        $relationship = $credential->worker?->activeEmploymentRelationship()->with('center')->first();

        return $resolveState->handle($credential->company, $credential->worker, null, $relationship?->center);
    }

    private function resetKioskState(bool $keepConfirmation = false): void
    {
        $this->accessCode = '';
        $this->pin = '';
        $this->credentialToken = null;
        $this->workerName = null;
        $this->localDate = null;
        $this->timezone = null;
        $this->allowedActions = [];

        if (! $keepConfirmation) {
            $this->confirmationMessage = null;
            $this->confirmationTime = null;
        }
    }

    private function eventMessage(string $eventType): string
    {
        return match ($eventType) {
            'clock_in' => 'Entrada registrada.',
            'clock_out' => 'Salida registrada.',
            'break_start' => 'Inicio de pausa registrado.',
            'break_end' => 'Fin de pausa registrado.',
            default => 'Registro guardado.',
        };
    }
}; ?>

<div class="mx-auto flex min-h-screen w-full max-w-3xl flex-col justify-center gap-6 p-6">
    <div class="text-center">
        <flux:heading size="xl">Kiosco</flux:heading>
        <flux:subheading>Registra entrada, salida y pausas con codigo y NIP.</flux:subheading>
    </div>

    @if ($confirmationMessage)
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-5 text-center text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            <p class="text-lg font-semibold">{{ $confirmationMessage }}</p>
            <p class="mt-1 text-sm">{{ $confirmationTime }}</p>
            <flux:button type="button" class="mt-4" wire:click="resetKiosk">Volver al inicio</flux:button>
        </div>
    @endif

    @if (! $credentialToken)
        <form wire:submit="identify" class="space-y-4 rounded-md border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:input wire:model="accessCode" label="Codigo de acceso o numero de empleado" autocomplete="off" autofocus />
            <flux:input wire:model="pin" label="NIP" type="password" autocomplete="off" />

            <flux:button variant="primary" type="submit" class="w-full">Continuar</flux:button>
        </form>
    @else
        <section class="space-y-5 rounded-md border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="text-center">
                <flux:heading>{{ $workerName }}</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $localDate }} · {{ $timezone }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                @if (in_array('clock_in', $allowedActions, true))
                    <flux:button type="button" variant="primary" wire:click="record('clock_in')">Registrar entrada</flux:button>
                @endif
                @if (in_array('break_start', $allowedActions, true))
                    <flux:button type="button" variant="primary" wire:click="record('break_start')">Iniciar pausa</flux:button>
                @endif
                @if (in_array('break_end', $allowedActions, true))
                    <flux:button type="button" variant="primary" wire:click="record('break_end')">Terminar pausa</flux:button>
                @endif
                @if (in_array('clock_out', $allowedActions, true))
                    <flux:button type="button" variant="primary" wire:click="record('clock_out')">Registrar salida</flux:button>
                @endif
            </div>

            @if (empty($allowedActions))
                <p class="text-center text-sm text-zinc-500">No hay acciones disponibles.</p>
            @endif

            <flux:button type="button" variant="ghost" class="w-full" wire:click="resetKiosk">Cancelar</flux:button>
        </section>
    @endif
</div>