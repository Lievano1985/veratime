<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Domains\Integrations\Actions\RevokeCompanyApiTokenAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public array $abilities = ['workers:read', 'time-events:write'];

    public ?string $plainTextToken = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        Gate::authorize('manageApiTokens', $this->currentCompanyOrFail($currentCompany));
    }

    public function createToken(CurrentCompany $currentCompany, IssueCompanyApiTokenAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('manageApiTokens', $company);

        $availableAbilities = array_keys($this->availableAbilities());
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in($availableAbilities)],
        ]);

        $issued = $action->handle(auth()->user(), $company, $validated['name'], $validated['abilities']);

        $this->plainTextToken = $issued->plainTextToken;
        $this->name = '';
        $this->abilities = ['workers:read', 'time-events:write'];
    }

    public function revokeToken(int $tokenId, CurrentCompany $currentCompany, RevokeCompanyApiTokenAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('manageApiTokens', $company);

        $action->handle(auth()->user(), $company, $tokenId);
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('manageApiTokens', $company);

        return [
            'tokens' => PersonalAccessToken::query()
                ->where('company_id', $company->id)
                ->where('tokenable_type', auth()->user()::class)
                ->where('tokenable_id', auth()->id())
                ->latest()
                ->get(),
            'availableAbilities' => $this->availableAbilities(),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany): Company
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function availableAbilities(): array
    {
        return [
            'centers:read' => 'Consultar centros de trabajo',
            'workers:read' => 'Consultar trabajadores',
            'workers:write' => 'Crear y actualizar trabajadores',
            'time-events:read' => 'Consultar eventos de jornada',
            'time-events:write' => 'Registrar eventos de jornada',
            'work-days:read' => 'Consultar jornadas calculadas',
            'alerts:read' => 'Consultar alertas preventivas',
            'incidents:read' => 'Consultar incidencias',
            'incidents:write' => 'Crear incidencias',
            'reports:read' => 'Consultar reportes y periodos',
            'exports:read' => 'Descargar exportaciones de nómina',
        ];
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div>
        <flux:heading size="xl">Credenciales API</flux:heading>
        <flux:subheading>Crea credenciales para integraciones de esta empresa. El secreto se muestra solo una vez.</flux:subheading>
    </div>

    @if ($plainTextToken)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-semibold">Guarda esta credencial ahora.</p>
            <p class="mt-1">No se podrá volver a consultar después de salir de esta pantalla.</p>
            <code class="mt-3 block break-all rounded bg-white p-3 text-xs text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">{{ $plainTextToken }}</code>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>Crear credencial</flux:heading>
            <p class="mt-1 text-sm text-surface-muted">Asigna únicamente los scopes necesarios para la integración.</p>

            <form wire:submit="createToken" class="mt-5 space-y-4">
                <flux:input wire:model="name" label="Nombre" placeholder="Reloj acceso principal" required />
                @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="space-y-2">
                    <p class="text-sm font-medium">Permisos</p>
                    @foreach ($availableAbilities as $ability => $label)
                        <flux:checkbox wire:model="abilities" value="{{ $ability }}" label="{{ $label }}" />
                    @endforeach
                    @error('abilities') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <flux:button variant="primary" type="submit">Generar credencial</flux:button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <flux:heading>Credenciales activas</flux:heading>
                <p class="mt-1 text-sm text-surface-muted">Solo se muestran las credenciales emitidas por tu usuario para esta empresa.</p>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($tokens as $token)
                    <div class="flex items-center justify-between gap-4 p-5">
                        <div>
                            <p class="font-medium">{{ $token->name }}</p>
                            <p class="mt-1 text-xs text-surface-muted">Creada {{ $token->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · Último uso {{ $token->last_used_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'sin uso' }}</p>
                            <p class="mt-2 text-xs text-surface-muted">{{ implode(', ', $token->abilities ?? []) }}</p>
                        </div>
                        <flux:button size="sm" variant="danger" wire:click="revokeToken({{ $token->id }})" wire:confirm="¿Revocar esta credencial? La integración dejará de funcionar.">Revocar</flux:button>
                    </div>
                @empty
                    <p class="p-5 text-sm text-surface-muted">Aún no has creado credenciales API para esta empresa.</p>
                @endforelse
            </div>
        </section>
    </div>
</section>
