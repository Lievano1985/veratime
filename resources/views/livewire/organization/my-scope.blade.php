<?php

use App\Domains\Organization\Actions\EnsureUserCanManageWorkerAction;
use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Support\RoleKey;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $date;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function with(
        CurrentCompany $currentCompany,
        ResolveUserOperationalScopeAction $resolveScope,
        EnsureUserCanManageWorkerAction $ensureCanManageWorker,
        ResolveEmploymentUnitsForDateAction $resolveEmploymentUnits,
    ): array {
        $company = $currentCompany->get();

        abort_unless($company, 403);
        abort_unless(auth()->user()->roleKeyForCompany($company) === RoleKey::SUPERVISOR, 403);

        $scope = $resolveScope->handle($company, auth()->user(), $this->date);
        $relationships = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $this->date)
            ->where(function ($query): void {
                $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $this->date);
            })
            ->with(['worker', 'center', 'employmentUnitAssignments.organizationalUnit'])
            ->orderBy('center_id')
            ->limit(150)
            ->get()
            ->filter(function (EmploymentRelationship $relationship) use ($company, $ensureCanManageWorker): bool {
                try {
                    $ensureCanManageWorker->handle(auth()->user(), $company, $relationship, $this->date);

                    return true;
                } catch (AuthorizationException) {
                    return false;
                }
            })
            ->values();

        return [
            'company' => $company,
            'scope' => $scope,
            'centers' => $company->centers()->whereIn('id', $scope['center_ids'])->orderBy('name')->get(),
            'units' => OrganizationalUnit::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $scope['organizational_unit_ids'])
                ->with(['center', 'parent'])
                ->orderBy('center_id')
                ->orderBy('name')
                ->get(),
            'relationships' => $relationships,
            'resolvedUnitsByRelationship' => $relationships
                ->mapWithKeys(fn (EmploymentRelationship $relationship) => [
                    $relationship->id => $resolveEmploymentUnits->handle($company, $relationship, $this->date),
                ])
                ->all(),
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl">Mi alcance</flux:heading>
        <flux:subheading>Consulta centros, unidades y trabajadores dentro de tu alcance operativo.</flux:subheading>
    </div>

    <div class="max-w-xs">
        <flux:input type="date" label="Fecha de consulta" wire:model.live="date" />
    </div>

    @if ($scope['scopes']->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
            Sin alcance operativo. Solicita a un administrador o RH que asigne un centro o una unidad.
        </div>
    @endif

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>Centros asignados</flux:heading>
            <div class="mt-4 space-y-2">
                @forelse ($centers as $center)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                        <p class="font-medium">{{ $center->name }}</p>
                        <p class="text-xs text-zinc-500">Incluye todas sus unidades y trabajadores aplicables.</p>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">Sin centros completos asignados.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>Unidades asignadas</flux:heading>
            <div class="mt-4 space-y-2">
                @forelse ($units as $unit)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                        <p class="font-medium">{{ $unit->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $unit->center?->name }} - incluye descendientes.</p>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">Sin unidades asignadas.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4">
            <flux:heading>Trabajadores dentro de mi alcance</flux:heading>
            <flux:subheading>Solo consulta. Las operaciones se habilitaran en modulos posteriores.</flux:subheading>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Centro</th>
                        <th class="px-4 py-3">Unidad principal</th>
                        <th class="px-4 py-3">Apoyos temporales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($relationships as $relationship)
                        @php($resolved = $resolvedUnitsByRelationship[$relationship->id] ?? null)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $relationship->worker?->full_name }}</span>
                                <span class="text-xs text-zinc-500">{{ $relationship->worker?->employee_code }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $relationship->center?->name }}</td>
                            <td class="px-4 py-3">{{ $resolved['primary']?->name ?? 'Sin unidad' }}</td>
                            <td class="px-4 py-3">
                                @if (($resolved['temporary_supports'] ?? collect())->isNotEmpty())
                                    {{ $resolved['temporary_supports']->pluck('name')->join(', ') }}
                                @else
                                    Sin apoyos vigentes
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-zinc-500">
                                No hay trabajadores dentro del alcance para esta fecha.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
