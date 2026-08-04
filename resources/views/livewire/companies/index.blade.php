<?php

use App\Domains\Companies\Actions\CreateCompanyAction;
use App\Domains\Companies\Actions\UpdateCompanyAction;
use App\Domains\Companies\Actions\UpdateCompanySettingsAction;
use App\Domains\LegalRules\Actions\ResolveCompanyLegalConfigurationAction;
use App\Domains\LegalRules\Actions\UpdateCompanyLegalParameterAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public array $createForm = [];
    public array $editForm = [];
    public array $settingsForm = [];
    public array $legalParameterForm = [];
    public bool $showCreateDrawer = false;
    public ?int $editingCompanyId = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        $this->createForm = $this->emptyCompanyForm();

        $company = $currentCompany->get();

        if ($company && Gate::allows('update', $company)) {
            $this->loadEditForm($company->id);
            $this->loadSettingsForm($company);
            $this->loadLegalParameterForm($company);
        }
    }

    public function create(CreateCompanyAction $action): void
    {
        Gate::authorize('create', Company::class);

        $validated = $this->validate([
            'createForm.name' => ['required', 'string', 'max:255'],
            'createForm.legal_name' => ['nullable', 'string', 'max:255'],
            'createForm.tax_id' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')],
            'createForm.timezone' => ['required', 'string', 'max:100'],
            'createForm.status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'cancelled'])],
        ])['createForm'];

        $action->handle(auth()->user(), $validated);

        $this->createForm = $this->emptyCompanyForm();
        $this->showCreateDrawer = false;

        Session::flash('status', 'Empresa creada.');
    }

    public function openCreateDrawer(): void
    {
        Gate::authorize('create', Company::class);

        $this->showCreateDrawer = true;
    }

    public function closeCreateDrawer(): void
    {
        $this->showCreateDrawer = false;
        $this->resetValidation('createForm');
    }

    public function loadEditForm(int $companyId): void
    {
        $company = $this->authorizedCompany($companyId, 'update');

        $this->editingCompanyId = $company->id;
        $this->editForm = [
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'tax_id' => $company->tax_id,
            'timezone' => $company->timezone,
            'status' => $company->status,
        ];
    }

    public function update(UpdateCompanyAction $action): void
    {
        $company = $this->authorizedCompany($this->editingCompanyId, 'update');

        $validated = $this->validate([
            'editForm.name' => ['required', 'string', 'max:255'],
            'editForm.legal_name' => ['nullable', 'string', 'max:255'],
            'editForm.tax_id' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')->ignore($company->id)],
            'editForm.timezone' => ['required', 'string', 'max:100'],
            'editForm.status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'cancelled'])],
        ])['editForm'];

        $company = $action->handle($company, $validated);

        if ($company->status !== 'active' && session('current_company_id') === $company->id) {
            session()->forget('current_company_id');
            app(CurrentCompany::class)->clear();
        }

        $this->loadEditForm($company->id);

        Session::flash('status', 'Empresa actualizada.');
    }

    public function updateSettings(UpdateCompanySettingsAction $action, CurrentCompany $currentCompany): void
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);
        Gate::authorize('update', $company);

        $validated = $this->validate([
            'settingsForm.payroll_period_type' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'custom'])],
            'settingsForm.default_timezone' => ['required', 'string', 'max:100'],
            'settingsForm.default_closure_day' => ['nullable', 'integer', 'between:1,31'],
            'settingsForm.work_days_auto_refresh_time' => ['nullable', 'date_format:H:i'],
            'settingsForm.allow_worker_corrections' => ['boolean'],
            'settingsForm.require_pin_for_kiosk' => ['boolean'],
            'settingsForm.require_pin_for_confirmation' => ['boolean'],
        ])['settingsForm'];

        $action->handle($company, $validated);
        $this->loadSettingsForm($company->refresh());

        Session::flash('status', 'Configuracion actualizada.');
    }

    public function updateLegalParameter(string $code, UpdateCompanyLegalParameterAction $action, CurrentCompany $currentCompany): void
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);
        Gate::authorize('update', $company);

        $validated = $this->validate([
            "legalParameterForm.{$code}.value" => ['required', 'integer'],
            "legalParameterForm.{$code}.effective_from" => ['required', 'date'],
            "legalParameterForm.{$code}.reason" => ['required', 'string', 'max:500'],
        ])['legalParameterForm'][$code];

        $action->handle(
            $company,
            $code,
            (int) $validated['value'],
            (string) $validated['effective_from'],
            trim((string) $validated['reason']),
            auth()->user(),
        );

        $this->loadLegalParameterForm($company);

        Session::flash('status', 'Parametro legal actualizado.');
    }

    public function with(CurrentCompany $currentCompany, ResolveCompanyLegalConfigurationAction $legalConfiguration): array
    {
        $company = $currentCompany->get();
        $canManageCurrentCompany = $company ? Gate::allows('update', $company) : false;

        return [
            'companies' => auth()->user()
                ->companiesWithActiveMembership()
                ->orderBy('name')
                ->get(),
            'currentCompany' => $company,
            'canCreateCompany' => Gate::allows('create', Company::class),
            'canManageCurrentCompany' => $canManageCurrentCompany,
            'canManageEditingCompany' => $this->canManageEditingCompany(),
            'legalConfiguration' => $company && $canManageCurrentCompany
                ? $legalConfiguration->handle($company)
                : null,
        ];
    }

    private function authorizedCompany(?int $companyId, string $ability): Company
    {
        abort_unless($companyId, 404);

        $company = auth()->user()
            ->companiesWithActiveMembership()
            ->whereKey($companyId)
            ->firstOrFail();

        Gate::authorize($ability, $company);

        return $company;
    }

    private function loadSettingsForm(Company $company): void
    {
        $settings = array_merge(Company::defaultSettings(), $company->setting?->toArray() ?? []);

        $this->settingsForm = [
            'payroll_period_type' => $settings['payroll_period_type'],
            'default_timezone' => $settings['default_timezone'] ?? $company->timezone,
            'default_closure_day' => $settings['default_closure_day'],
            'work_days_auto_refresh_time' => $settings['work_days_auto_refresh_time']
                ? substr((string) $settings['work_days_auto_refresh_time'], 0, 5)
                : null,
            'allow_worker_corrections' => (bool) $settings['allow_worker_corrections'],
            'require_pin_for_kiosk' => (bool) $settings['require_pin_for_kiosk'],
            'require_pin_for_confirmation' => (bool) $settings['require_pin_for_confirmation'],
        ];
    }

    private function loadLegalParameterForm(Company $company): void
    {
        $configuration = app(ResolveCompanyLegalConfigurationAction::class)->handle($company);

        $this->legalParameterForm = collect($configuration['parameters'])
            ->mapWithKeys(fn (array $parameter, string $code): array => [$code => [
                'value' => $parameter['value'],
                'effective_from' => $parameter['effective_from'],
                'reason' => $parameter['reason'] ?: 'Configuracion interna de empresa',
            ]])
            ->all();
    }

    private function minutesLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return 'No aplica';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining === 0 ? "{$hours} h" : "{$hours} h {$remaining} min";
    }

    private function ruleValueLabel(array $rule): string
    {
        $value = $rule['value'] ?? [];

        if (array_key_exists('minutes', $value)) {
            return $this->minutesLabel((int) $value['minutes']);
        }

        if (array_key_exists('start', $value) && array_key_exists('end', $value)) {
            return "{$value['start']} - {$value['end']}";
        }

        return 'Configurada';
    }

    private function canManageEditingCompany(): bool
    {
        if (! $this->editingCompanyId) {
            return false;
        }

        $company = Company::query()->find($this->editingCompanyId);

        return $company ? Gate::allows('update', $company) : false;
    }

    private function emptyCompanyForm(): array
    {
        return [
            'name' => '',
            'legal_name' => '',
            'tax_id' => '',
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
        ];
    }
}; ?>

<section class="w-full space-y-8 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Empresas</flux:heading>
            <flux:subheading>Administra la empresa activa y su configuracion inicial.</flux:subheading>
        </div>

        @if ($canCreateCompany)
            <flux:button type="button" variant="primary" wire:click="openCreateDrawer">
                Nueva empresa
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            <section class="rounded-lg border border-primary-border bg-primary-soft p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4">
                    <flux:heading>Empresas autorizadas</flux:heading>
                    <flux:subheading>Se listan las empresas donde tu relacion esta activa; las inactivas no aparecen en el selector operativo.</flux:subheading>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($companies as $company)
                        <div class="flex w-full items-center justify-between gap-4 py-3 text-left">
                            <span>
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $company->name }}</span>
                                <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $company->tax_id ?: 'Sin RFC' }}</span>
                            </span>

                            <span class="flex items-center gap-3">
                                <x-ui.badge variant="{{ $company->status === 'active' ? 'success' : 'neutral' }}">
                                    {{ $company->status }}
                                </x-ui.badge>

                                @can('update', $company)
                                    <flux:button type="button" size="sm" wire:click="loadEditForm({{ $company->id }})">Editar</flux:button>
                                @endcan
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($editingCompanyId && $canManageEditingCompany)
                <section class="rounded-lg border border-zinc-200 bg-zinc-50/60 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <div class="mb-4">
                        <flux:heading>Datos basicos</flux:heading>
                        <flux:subheading>Editar informacion general y estado operativo de la empresa.</flux:subheading>
                    </div>

                    <form wire:submit="update" class="space-y-4">
                        <flux:input wire:model="editForm.name" label="Nombre comercial" required />
                        <flux:input wire:model="editForm.legal_name" label="Razon social" />
                        <flux:input wire:model="editForm.tax_id" label="RFC" />
                        <flux:input wire:model="editForm.timezone" label="Zona horaria" required />

                        <div>
                            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                            <x-ui.select wire:model="editForm.status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                                <option value="suspended">Suspendida</option>
                                <option value="cancelled">Cancelada</option>
                            </x-ui.select>
                            @error('editForm.status')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:button type="submit" variant="primary">Guardar empresa</flux:button>
                    </form>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            @if ($currentCompany && $canManageCurrentCompany)
                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <flux:heading>Configuracion</flux:heading>
                        <flux:subheading>Parametros iniciales de cierre, kiosco, correcciones y conformidad.</flux:subheading>
                    </div>

                    <form wire:submit="updateSettings" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Periodo de cierre</label>
                            <x-ui.select wire:model="settingsForm.payroll_period_type">
                                <option value="weekly">Semanal</option>
                                <option value="biweekly">Quincenal</option>
                                <option value="monthly">Mensual</option>
                                <option value="custom">Personalizado</option>
                            </x-ui.select>
                            @error('settingsForm.payroll_period_type')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:input wire:model="settingsForm.default_timezone" label="Zona horaria" required />
                        <flux:input wire:model="settingsForm.default_closure_day" label="Dia de cierre" type="number" min="1" max="31" />
                        <flux:input wire:model="settingsForm.work_days_auto_refresh_time" label="Hora automatica de jornadas" type="time" />

                        <div class="space-y-3">
                            <flux:checkbox wire:model="settingsForm.allow_worker_corrections" label="Permitir solicitudes de correccion" />
                            <flux:checkbox wire:model="settingsForm.require_pin_for_kiosk" label="Requerir NIP en kiosco" />
                            <flux:checkbox wire:model="settingsForm.require_pin_for_confirmation" label="Requerir NIP para conformidad" />
                        </div>

                        <flux:button type="submit" variant="primary">Guardar configuracion</flux:button>
                    </form>
                </section>
            @endif

            @if ($currentCompany && $canManageCurrentCompany && $legalConfiguration)
                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <flux:heading>Configuracion legal</flux:heading>
                        <flux:subheading>Mexico preconfigurado. Las reglas base son protegidas; solo se ajustan parametros internos permitidos.</flux:subheading>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Reglas base del pais</h3>
                                <x-ui.badge variant="neutral">MX</x-ui.badge>
                            </div>

                            <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
                                <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                        <tr>
                                            <th class="px-3 py-2">Regla</th>
                                            <th class="px-3 py-2">Valor</th>
                                            <th class="px-3 py-2">Version</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                                        @foreach ($legalConfiguration['rules'] as $rule)
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <span class="block font-medium">{{ $rule['name'] }}</span>
                                                    <span class="text-xs text-zinc-500">{{ $rule['code'] }}</span>
                                                </td>
                                                <td class="px-3 py-2">{{ $this->ruleValueLabel($rule) }}</td>
                                                <td class="px-3 py-2">
                                                    <x-ui.badge variant="info">v{{ $rule['version'] }}</x-ui.badge>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Parametros internos</h3>

                            <div class="space-y-3">
                                @foreach ($legalConfiguration['parameters'] as $code => $parameter)
                                    <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                                        <div class="mb-3 flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $parameter['definition']['label'] }}</p>
                                                <p class="text-xs text-zinc-500">{{ $parameter['definition']['description'] }}</p>
                                            </div>
                                            @if ($parameter['definition']['protected_max'])
                                                <x-ui.badge variant="warning">Protegido</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="neutral">Interno</x-ui.badge>
                                            @endif
                                        </div>

                                        <div class="grid gap-3 md:grid-cols-[120px_150px_minmax(0,1fr)_auto] md:items-end">
                                            <flux:input
                                                wire:model="legalParameterForm.{{ $code }}.value"
                                                label="Minutos"
                                                type="number"
                                                min="{{ $parameter['definition']['min'] }}"
                                                max="{{ $parameter['definition']['max'] }}"
                                            />
                                            <flux:input wire:model="legalParameterForm.{{ $code }}.effective_from" label="Vigente desde" type="date" />
                                            <flux:input wire:model="legalParameterForm.{{ $code }}.reason" label="Motivo" />
                                            <flux:button type="button" variant="primary" wire:click="updateLegalParameter('{{ $code }}')">
                                                Guardar
                                            </flux:button>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500">
                                            Limite permitido: {{ $parameter['definition']['min'] }} a {{ $parameter['definition']['max'] }} minutos.
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>

    @if ($canCreateCompany)
        <x-side-panel
            wire:model="showCreateDrawer"
            title="Nueva empresa"
            subheading="La empresa queda asociada a tu usuario como propietario."
            labelledby="create-company-title"
        >
            <form wire:submit="create" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-4 p-6">
                    <flux:input wire:model="createForm.name" label="Nombre comercial" required />
                    <flux:input wire:model="createForm.legal_name" label="Razon social" />
                    <flux:input wire:model="createForm.tax_id" label="RFC" />
                    <flux:input wire:model="createForm.timezone" label="Zona horaria" required />
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="closeCreateDrawer">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Crear empresa
                    </flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
