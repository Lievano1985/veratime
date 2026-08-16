<?php

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
    public array $settingsForm = [];
    public array $legalParameterForm = [];

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('update', $company);

        $this->loadSettingsForm($company);
        $this->loadLegalParameterForm($company);
    }

    public function updateSettings(UpdateCompanySettingsAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

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

        Session::flash('status', 'Configuracion de empresa actualizada.');
    }

    public function updateLegalParameter(string $code, UpdateCompanyLegalParameterAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

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
            $validated['effective_from'],
            $validated['reason'],
            auth()->user(),
        );

        $this->loadLegalParameterForm($company);

        Session::flash('status', 'Parametro legal actualizado.');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('update', $company);

        return [
            'currentCompany' => $company,
            'legalConfiguration' => app(ResolveCompanyLegalConfigurationAction::class)->handle($company),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany): Company
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

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
}; ?>

<section class="w-full space-y-6 p-6">
    <div>
        <flux:heading size="xl">Configuracion de empresa</flux:heading>
        <flux:subheading>Parametros operativos, cierre, kiosco y configuracion legal de la empresa activa.</flux:subheading>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4">
                <flux:heading>Operacion</flux:heading>
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

                    <div class="overflow-x-auto rounded-md border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
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

                    <div class="grid gap-3 lg:grid-cols-2">
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

                                <div class="space-y-3">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <flux:input
                                            wire:model="legalParameterForm.{{ $code }}.value"
                                            label="Minutos"
                                            type="number"
                                            min="{{ $parameter['definition']['min'] }}"
                                            max="{{ $parameter['definition']['max'] }}"
                                        />
                                        <flux:input wire:model="legalParameterForm.{{ $code }}.effective_from" label="Vigente desde" type="date" />
                                    </div>

                                    <flux:input wire:model="legalParameterForm.{{ $code }}.reason" label="Motivo" />

                                    <div class="flex justify-end">
                                        <flux:button type="button" variant="primary" wire:click="updateLegalParameter('{{ $code }}')">
                                            Guardar
                                        </flux:button>
                                    </div>
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
    </div>
</section>
